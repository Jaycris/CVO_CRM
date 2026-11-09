<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\CommissionDisputeOffset;
use App\Models\CommissionRelease;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\SalesTarget;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CommissionUnreleasedOffsets
{
    public function afterDispute(SalesPayment $payment, array $freshAdjustmentIds = [], string $status = 'Dispute'): void
    {
        $entries = CommissionAdjustment::where('sales_payment_id', $payment->id)->whereIn('kind', CommissionAdjustment::DEDUCTION_KINDS)->get()->unique('agent_id');
        foreach ($entries as $entry) {
            if (CommissionRelease::latestFor($payment->id, $entry->agent_id)?->status === 'unreleased') {
                // An unpaid commission that is itself cancelled can no longer fund an older debt.
                CommissionDisputeOffset::where('sales_payment_id', $payment->id)->where('agent_id', $entry->agent_id)
                    ->whereNull('voided_at')->update(['voided_at' => now(), 'void_reason' => $status === 'Refund' ? 'The funding commission was refunded before release.' : 'The funding commission was disputed before release.']);
            }
            $this->settleForAgent(User::withTrashed()->findOrFail($entry->agent_id), $payment->id, $freshAdjustmentIds);
        }
    }

    public function settleForAgent(User $agent, ?int $excludedPaymentId = null, array $freshAdjustmentIds = []): void
    {
        DB::transaction(function () use ($agent, $excludedPaymentId, $freshAdjustmentIds) {
            // Serialize reservations across this agent's disputes, including Frankie shares.
            User::withTrashed()->whereKey($agent->id)->lockForUpdate()->firstOrFail();
            $entries = CommissionAdjustment::where('agent_id', $agent->id)->orderBy('id')->get();
            $debts = $entries->groupBy('sales_payment_id')->map->last()->filter(function ($entry) use ($agent, $freshAdjustmentIds) {
                if (! $entry->isDeduction() || (float) $entry->usd_amount >= 0) return false;
                if (! in_array($entry->id, $freshAdjustmentIds, true)
                    && ! SalesPayment::withTrashed()->whereKey($entry->sales_payment_id)->whereIn('status', ['Dispute', 'Refund'])->exists()) return false;
                // Historical debt may already have been collected from a later payroll. The release API
                // verifies sale commissions, not debt repayment. Do not re-collect it on a later fetch.
                if (! in_array($entry->id, $freshAdjustmentIds, true) && SalesActivity::where('payment_status', 'Payment Success')
                    ->whereDate('sold_date', '>=', $entry->effective_date->copy()->startOfMonth()->toDateString())
                    ->whereDate('sold_date', '<=', now()->toDateString())
                    ->where(fn ($query) => $query->where(fn ($query) => $query->where('agent_id', $agent->id)->where('agent_credit_amount', '>', 0))
                        ->orWhere(fn ($query) => $query->where('frankie_agent_id', $agent->id)->where('frankie_credit_amount', '>', 0)))
                    ->exists()) return false;
                $release = CommissionRelease::latestFor($entry->sales_payment_id, $entry->agent_id);
                // Legacy deductions may be offset only when their amount already agrees with PHREMS.
                return $release?->source === 'phrems' && $release->status === 'released'
                    && (int) round(abs((float) $entry->usd_amount) * 100) === (int) round((float) ($release->commission_breakdown['usd_total'] ?? -1) * 100)
                    && $release->released_at && $release->released_at->toDateString() <= $entry->effective_date->toDateString();
            });
            $debts = $debts->filter(fn ($entry) => abs((float) $entry->usd_amount) > (float) CommissionDisputeOffset::where('commission_adjustment_id', $entry->id)->whereNull('voided_at')->sum('usd_amount'));
            if ($debts->isEmpty()) return;
            $latestDebtMonth = $debts->max(fn ($entry) => $entry->effective_date->copy()->startOfMonth()->toDateString());
            $activities = SalesActivity::with(['agent.commissionProfile.rules', 'frankieAgent.commissionProfile.rules', 'service', 'brand', 'payment'])
                ->where('payment_status', 'Payment Success')->whereNotNull('sales_payment_id')
                ->whereDate('sold_date', '<', $latestDebtMonth)
                ->where(fn ($query) => $query->where('agent_id', $agent->id)->orWhere(fn ($query) => $query->where('frankie_agent_id', $agent->id)->where('frankie_credit_amount', '>', 0)))
                ->orderBy('sold_date')->orderBy('id')->get();
            if ($activities->isEmpty()) return;

            $candidates = collect();
            foreach ($activities->groupBy(fn ($activity) => $activity->sold_date->format('Y-m')) as $period => $monthly) {
                if (! $monthly->contains(fn ($activity) => $activity->sales_payment_id !== $excludedPaymentId && $activity->payment?->status === 'Payment Success')) continue;
                $month = Carbon::createFromFormat('!Y-m', $period);
                // Never spend a cached Unreleased status: the slip may have been sent since the last fetch.
                $statuses = app(PhremsCommissionReleases::class)->syncAgentMonth($agent, $month);
                $targets = SalesMtdCalculator::agentTargetRows(SalesTarget::whereDate('target_month', $month)->where('target_type', 'agent')->get());
                $gross = SalesMtdCalculator::statementRows($monthly, $targets)->keyBy(fn ($row) => $row['activity']->id.':'.$row['agent_id']);
                $calculated = SalesMtdCalculator::withMtdDeductions($gross->values(), $targets, SalesMtdDeductions::entries($month));
                $opening = CommissionBalances::apply(collect(), $month, null, null, $agent->id)
                    ->filter(fn ($row) => $row['commission_profile_name'] === 'Negative balance brought forward')->sum('usd_total');
                $periodEvents = CommissionAdjustment::where('agent_id', $agent->id)
                    ->whereDate('effective_date', '>=', $month->toDateString())->whereDate('effective_date', '<=', $month->copy()->endOfMonth()->toDateString())
                    ->where(fn ($query) => $query->whereNull('settlement_basis')->orWhere('settlement_basis', '!=', 'unreleased'))->sum('usd_amount');
                $coveredInPeriod = CommissionDisputeOffset::where('agent_id', $agent->id)->whereNull('voided_at')
                    ->whereDate('dispute_date', '>=', $month->toDateString())->whereDate('dispute_date', '<=', $month->copy()->endOfMonth()->toDateString())->sum('usd_amount');
                $priorObligations = max(0, -(int) round(((float) $opening + (float) $periodEvents + (float) $coveredInPeriod) * 100));
                foreach ($calculated->where('agent_id', $agent->id) as $row) {
                    $paymentId = $row['activity']->sales_payment_id;
                    if ($paymentId === $excludedPaymentId || $row['activity']->payment?->status !== 'Payment Success') continue;
                    if (($statuses[$paymentId.':'.$agent->id] ?? null) !== 'unreleased') continue;
                    $reserved = (float) CommissionDisputeOffset::where('agent_id', $agent->id)->where('sales_payment_id', $paymentId)->whereNull('voided_at')->sum('usd_amount');
                    $payable = (float) $row['exchange_rate'] > 0 ? (float) $row['net_commission'] / (float) $row['exchange_rate'] : 0;
                    $available = max(0, (int) floor(round($payable * 100, 6)) - (int) round($reserved * 100));
                    $earmarked = min($available, $priorObligations);
                    $priorObligations -= $earmarked;
                    $candidates->push(['row' => $row, 'gross' => $gross->get($row['activity']->id.':'.$agent->id), 'available' => $available - $earmarked]);
                }
            }
            foreach ($debts->sortBy('effective_date') as $debt) {
                $reserved = (float) CommissionDisputeOffset::where('commission_adjustment_id', $debt->id)->whereNull('voided_at')->sum('usd_amount');
                $remaining = max(0, (int) round((abs((float) $debt->usd_amount) - $reserved) * 100));
                foreach ($candidates as $index => $candidate) {
                    $row = $candidate['row'];
                    $activity = $row['activity'];
                    if ($remaining === 0) break;
                    if ($activity->sold_date->format('Y-m') >= $debt->effective_date->format('Y-m')) continue;
                    $amount = min($remaining, $candidate['available']);
                    if ($amount <= 0) continue;
                    $release = CommissionRelease::latestFor($activity->sales_payment_id, $agent->id);
                    // A commission funding a settlement must keep the gross basis that was reserved.
                    // Later catalog/profile edits must not change the already committed money.
                    $snapshots = $activity->fresh()->commission_snapshot ?: [];
                    if (! isset($snapshots[$agent->id])) {
                        $snapshots[$agent->id] = collect($candidate['gross'])->except(['activity', 'agent', 'commission_profile', 'paid_breakdown'])->all() + [
                            'author_name' => $activity->author_name, 'book_title' => $activity->book_title,
                            'endorsement_code' => $activity->endorsement_code, 'original_sold_date' => $activity->sold_date->toDateString(),
                            'paid_usd_total' => (float) $row['usd_total'],
                            'paid_breakdown' => collect($row)->except(['activity', 'agent', 'commission_profile', 'paid_breakdown'])->all(),
                        ];
                        $activity->update(['commission_snapshot' => $snapshots]);
                    }
                    CommissionDisputeOffset::create([
                        'commission_adjustment_id' => $debt->id, 'sales_payment_id' => $activity->sales_payment_id,
                        'agent_id' => $agent->id, 'commission_release_id' => $release->id,
                        'commission_date' => $activity->sold_date->toDateString(), 'dispute_date' => $debt->effective_date->toDateString(),
                        'usd_amount' => $amount / 100,
                        'commission_snapshot' => collect($row)->except(['activity', 'agent', 'commission_profile', 'paid_breakdown'])->all() + [
                            'brand_id' => $activity->brand_id, 'author_name' => $activity->author_name,
                            'book_title' => $activity->book_title, 'endorsement_code' => $activity->endorsement_code,
                        ],
                    ]);
                    $candidate['available'] -= $amount;
                    $candidates->put($index, $candidate);
                    $remaining -= $amount;
                }
            }
        });
    }
}
