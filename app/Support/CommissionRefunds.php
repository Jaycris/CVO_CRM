<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\CommissionRelease;
use App\Models\CommissionDisputeOffset;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionRefunds
{
    private const FINANCIAL_FIELDS = ['usd_total', 'service_commission', 'markup_commission', 'php_total',
        'hold_amount', 'net_commission', 'exchange_rate', 'card_payment_hold_percent'];

    public static function pending(CarbonInterface $through, ?int $agentId = null, ?User $viewer = null, ?int $brandId = null): Collection
    {
        $activities = SalesActivity::with(['payment' => fn ($query) => $query->withTrashed()])
            ->whereNotNull('commission_snapshot')->whereNotNull('sales_payment_id')
            ->whereHas('payment', fn ($query) => $query->withTrashed()->where('status', 'Refund'))
            ->whereDate('sold_date', '<=', $through->toDateString())
            ->when($agentId, fn ($query) => $query->where(fn ($query) => $query->where('agent_id', $agentId)
                ->orWhere(fn ($query) => $query->where('frankie_agent_id', $agentId)->where('frankie_credit_amount', '>', 0))))
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($viewer && ! $brandId, fn ($query) => BrandScope::apply($query, $viewer))->get();
        // The old Refund path could delete an unfrozen activity even after PHREMS sent its
        // commission. A missing activity must not hide that unresolved refund from payroll.
        $missing = CommissionRelease::query()->join('sales_payments', 'sales_payments.id', '=', 'commission_releases.sales_payment_id')
            ->where('sales_payments.status', 'Refund')->where('commission_releases.status', 'released')
            ->whereDate('sales_payments.sold_date', '<=', $through->toDateString())
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('sales_activities')
                ->whereColumn('sales_activities.sales_payment_id', 'sales_payments.id')->whereNotNull('sales_activities.commission_snapshot'))
            ->when($agentId, fn ($query) => $query->where('commission_releases.agent_id', $agentId))
            ->when($brandId, fn ($query) => $query->where('commission_releases.brand_id', $brandId))
            ->when($viewer && ! $brandId, fn ($query) => BrandScope::apply($query, $viewer, 'commission_releases.brand_id'))
            ->select(['commission_releases.sales_payment_id', 'commission_releases.agent_id', 'commission_releases.brand_id', 'sales_payments.sold_date'])
            ->get()->unique(fn ($release) => $release->sales_payment_id.':'.$release->agent_id);
        foreach ($missing as $release) {
            $activity = new SalesActivity(['sales_payment_id' => $release->sales_payment_id, 'agent_id' => $release->agent_id,
                'brand_id' => $release->brand_id, 'sold_date' => $release->sold_date]);
            $activity->setAttribute('refund_history_missing', true);
            $activities->push($activity);
        }
        $last = CommissionAdjustment::whereIn('sales_payment_id', $activities->pluck('sales_payment_id'))->orderBy('id')->get()
            ->groupBy(fn ($entry) => $entry->sales_payment_id.':'.$entry->agent_id)->map->last();
        $releases = CommissionRelease::whereIn('sales_payment_id', $activities->pluck('sales_payment_id'))->orderBy('id')->get()
            ->groupBy(fn ($entry) => $entry->sales_payment_id.':'.$entry->agent_id)->map->last();
        $funding = CommissionDisputeOffset::whereNull('voided_at')->whereIn('sales_payment_id', $activities->pluck('sales_payment_id'))->get()
            ->groupBy(fn ($offset) => $offset->sales_payment_id.':'.$offset->agent_id);
        return $activities->filter(function ($activity) use ($last, $releases, $funding, $agentId) {
            foreach (self::shares($activity) as $id) {
                if ($agentId && (int) $id !== $agentId) continue;
                $key = $activity->sales_payment_id.':'.$id;
                $entry = $last->get($key);
                if (! $entry?->isDeduction()) return true;
                if ($releases->get($key)?->status === 'unreleased'
                    && (! self::completeUnreleased($activity, $id, $entry) || $funding->has($key))) return true;
            }
            return false;
        });
    }

    public function reconcileUnreleased(CarbonInterface $month, ?int $agentId = null): int
    {
        $count = 0;
        foreach (self::pending($month->copy()->endOfMonth(), $agentId)->filter(fn ($activity) => $activity->sold_date->format('Y-m') === $month->format('Y-m')) as $activity) {
            if ($activity->refund_history_missing) continue;
            // Existing released refunds require an explicit historical date. Never guess it from deleted_at.
            if (collect(self::shares($activity))->contains(fn ($id) => CommissionRelease::latestFor($activity->sales_payment_id, $id)?->status !== 'unreleased')) continue;
            if ($this->reconcile($activity->sales_payment_id, null, 'Automatically reconciled an existing unpaid refund after PHREMS verification.')) $count++;
        }
        return $count;
    }

    public function reconcile(int $paymentId, ?CarbonInterface $refundedOn = null, string $reason = ''): bool
    {
        return DB::transaction(function () use ($paymentId, $refundedOn, $reason) {
            $activity = SalesActivity::where('sales_payment_id', $paymentId)->firstOrFail();
            User::withTrashed()->whereIn('id', self::shares($activity))->orderBy('id')->lockForUpdate()->get();
            $payment = SalesPayment::withTrashed()->whereKey($paymentId)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'Refund' || ! $activity->commission_snapshot) {
                throw ValidationException::withMessages(['refund' => 'Only a refunded payment with its original commission history can be reconciled.']);
            }
            if ($refundedOn && ($refundedOn->toDateString() < $activity->sold_date->toDateString() || $refundedOn->isFuture())) {
                throw ValidationException::withMessages(['refunded_on' => 'The refund date must be between the sale date and today.']);
            }
            $before = CommissionAdjustment::where('sales_payment_id', $paymentId)->pluck('id');
            $activeBefore = CommissionDisputeOffset::where('sales_payment_id', $paymentId)->whereNull('voided_at')->count();
            // Always verify the source, including when a stale ledger currently says "recorded".
            app(PhremsCommissionReleases::class)->syncForActivity($activity);
            foreach (self::shares($activity) as $id) {
                $entry = CommissionAdjustment::where('sales_payment_id', $paymentId)->where('agent_id', $id)->latest('id')->first();
                if (CommissionRelease::latestFor($paymentId, $id)?->status !== 'unreleased'
                    || ! $entry?->isDeduction() || self::completeUnreleased($activity, $id, $entry)) continue;
                $expected = self::expectedBreakdown($activity, $id);
                if (! self::validBreakdown($expected)) {
                    throw ValidationException::withMessages(['refund' => 'The original unpaid refund calculation is incomplete. Admin must review its commission history before correcting it.']);
                }
                if (CommissionDisputeOffset::where('commission_adjustment_id', $entry->id)->whereNull('voided_at')->exists()) {
                    throw ValidationException::withMessages(['refund' => 'This older deduction has its own allocated offsets. Review those allocations before replacing it with the unpaid refund reversal.']);
                }
                if ($entry->effective_date->toDateString() !== $activity->sold_date->toDateString()
                    && SalesActivity::where('payment_status', 'Payment Success')->where('sales_payment_id', '!=', $paymentId)
                        ->whereHas('payment', fn ($query) => $query->where('status', 'Payment Success'))
                        ->whereDate('sold_date', '>=', $entry->effective_date->copy()->startOfMonth()->toDateString())
                        ->where(fn ($query) => $query->where('agent_id', $id)->orWhere('frankie_agent_id', $id))->exists()) {
                    throw ValidationException::withMessages(['refund' => 'Other earnings may already have covered this older deduction. Admin must review the prior payroll before moving its refund reversal to the original period.']);
                }
                // Preserve the old entry, cancel it in the same period, then let the normal
                // refund path record the complete original-period reversal exactly once.
                $original = $entry->original_commission;
                $original['refund_correction_reverses'] = $entry->id;
                CommissionAdjustment::create(['sales_payment_id' => $paymentId, 'agent_id' => $id, 'brand_id' => $entry->brand_id,
                    'kind' => 'reinstatement', 'effective_date' => $entry->effective_date, 'settlement_basis' => $entry->settlement_basis,
                    'usd_amount' => abs((float) $entry->usd_amount), 'original_commission' => $original]);
            }
            CommissionDisputes::transition($payment, 'Refund', $refundedOn, true);
            $new = CommissionAdjustment::where('sales_payment_id', $paymentId)->whereNotIn('id', $before)->get();
            foreach ($new as $entry) {
                $original = $entry->original_commission;
                $original['refund_reconciliation'] = ['reason' => $reason, 'created_by' => auth()->id(),
                    'refunded_on' => $refundedOn?->toDateString(), 'recorded_at' => now()->toIso8601String()];
                $entry->update(['original_commission' => $original]);
            }
            return $new->isNotEmpty() || CommissionDisputeOffset::where('sales_payment_id', $paymentId)->whereNull('voided_at')->count() < $activeBefore;
        });
    }

    private static function expectedBreakdown(SalesActivity $activity, int $agentId): array
    {
        $snapshot = $activity->commission_snapshot[$agentId] ?? [];
        return $snapshot['paid_breakdown'] ?? $snapshot;
    }

    private static function validBreakdown(array $breakdown): bool
    {
        foreach (self::FINANCIAL_FIELDS as $field) {
            if (! is_numeric($breakdown[$field] ?? null) || ! is_finite((float) $breakdown[$field]) || (float) $breakdown[$field] < 0) return false;
        }
        return (float) $breakdown['exchange_rate'] > 0
            && abs((float) $breakdown['service_commission'] + (float) $breakdown['markup_commission'] - (float) $breakdown['usd_total']) <= .02
            && abs((float) $breakdown['php_total'] - (float) $breakdown['usd_total'] * (float) $breakdown['exchange_rate']) <= .02
            && abs((float) $breakdown['net_commission'] + (float) $breakdown['hold_amount'] - (float) $breakdown['php_total']) <= .02;
    }

    private static function completeUnreleased(SalesActivity $activity, int $agentId, CommissionAdjustment $entry): bool
    {
        $expected = self::expectedBreakdown($activity, $agentId);
        $recorded = $entry->original_commission['settlement_breakdown'] ?? [];
        return $entry->settlement_basis === 'unreleased' && isset($expected['usd_total'])
            && $entry->effective_date->toDateString() === $activity->sold_date->toDateString()
            && (int) round((float) $entry->usd_amount * 100) === -(int) round((float) $expected['usd_total'] * 100)
            && self::validBreakdown($recorded)
            && (int) round((float) $recorded['usd_total'] * 100) === (int) round((float) $expected['usd_total'] * 100);
    }

    private static function shares(SalesActivity $activity): array
    {
        return array_values(array_unique(array_filter([$activity->agent_id,
            (float) $activity->frankie_credit_amount > 0 ? $activity->frankie_agent_id : null])));
    }
}
