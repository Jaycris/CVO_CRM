<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\CommissionRelease;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\SalesTarget;
use Illuminate\Validation\ValidationException;

class CommissionDisputes
{
    // Called while the payment row is locked, before its status is changed.
    public static function transition(SalesPayment $payment, string $status): void
    {
        if (! in_array($status, ['Dispute', 'Payment Success'], true)) {
            return;
        }

        $activity = SalesActivity::where('sales_payment_id', $payment->id)->first();

        if (! $activity) {
            return;
        }

        $snapshots = $activity->commission_snapshot;
        $paidRows = collect();

        // A payment can have separate main-agent and Frankie commission releases.
        if ($status === 'Dispute') {
            $shareIds = array_filter([$activity->agent_id, (float) $activity->frankie_credit_amount > 0 ? $activity->frankie_agent_id : null]);
            $needsLookup = collect(array_unique($shareIds))->contains(fn ($agentId) => CommissionAdjustment::where('sales_payment_id', $payment->id)
                ->where('agent_id', $agentId)->latest('id')->first()?->kind !== 'dispute');
            if ($needsLookup) app(PhremsCommissionReleases::class)->syncForActivity($activity);
            foreach (array_unique($shareIds) as $agentId) {
                $last = CommissionAdjustment::where('sales_payment_id', $payment->id)->where('agent_id', $agentId)->latest('id')->first();
                if ($last?->kind !== 'dispute' && ! in_array(CommissionRelease::latestFor($payment->id, $agentId)?->status, ['released', 'unreleased'], true)) {
                    throw ValidationException::withMessages([
                        'status' => 'PHREMS release status could not be verified for every agent on this payment. Ask Admin to review Commission Releases.',
                    ]);
                }
            }
        }

        if ($status === 'Dispute' && $payment->status === 'Payment Success') {
            $month = $activity->sold_date->copy()->startOfMonth();
            $agentIds = array_filter([$activity->agent_id, $activity->frankie_agent_id]);
            $activities = SalesActivity::with(['agent.commissionProfile.rules', 'frankieAgent.commissionProfile.rules', 'service'])
                ->where('payment_status', 'Payment Success')
                ->whereDate('sold_date', '>=', $month->toDateString())
                ->whereDate('sold_date', '<=', $month->copy()->endOfMonth()->toDateString())
                ->where(fn ($query) => $query->whereIn('agent_id', $agentIds)->orWhereIn('frankie_agent_id', $agentIds))
                ->get();
            $targets = SalesMtdCalculator::agentTargetRows(SalesTarget::whereDate('target_month', $month)->get());
            $grossRows = SalesMtdCalculator::statementRows($activities, $targets);
            $paidRows = SalesMtdCalculator::withMtdDeductions($grossRows, $targets, SalesMtdDeductions::entries($month))
                ->filter(fn ($row) => $row['activity']->id === $activity->id)->keyBy('agent_id');
            $newSnapshots = $grossRows
                ->filter(fn ($row) => $row['activity']->id === $activity->id)
                ->mapWithKeys(function ($row) use ($paidRows) {
                    $snapshot = collect($row)->except(['activity', 'agent', 'commission_profile', 'paid_breakdown'])->all();
                    $snapshot['author_name'] = $row['activity']->author_name;
                    $snapshot['book_title'] = $row['activity']->book_title;
                    $snapshot['endorsement_code'] = $row['activity']->endorsement_code;
                    $snapshot['original_sold_date'] = $row['activity']->sold_date->toDateString();
                    $snapshot['paid_usd_total'] = (float) ($paidRows->get($row['agent_id'])['usd_total'] ?? $row['usd_total']);
                    $snapshot['paid_breakdown'] = collect($paidRows->get($row['agent_id']) ?? $row)->except(['activity', 'agent', 'commission_profile', 'paid_breakdown'])->all();

                    return [$row['agent_id'] => $snapshot];
                })->all();
            // Retain historical snapshots when a restored payment is disputed again.
            $snapshots = array_replace($newSnapshots, $snapshots ?: []);
            $activity->update(['commission_snapshot' => $snapshots]);
        }

        foreach ($snapshots ?: [] as $agentId => $snapshot) {
            $last = CommissionAdjustment::where('sales_payment_id', $payment->id)
                ->where('agent_id', $agentId)->latest('id')->first();
            $kind = $status === 'Dispute' ? 'dispute' : 'reinstatement';

            if ($last?->kind === $kind || ($kind === 'reinstatement' && ! $last)) {
                continue;
            }

            if ($kind === 'reinstatement') {
                // Restore precisely the deduction previously recorded, in the same original period for unpaid commissions.
                $basis = $last->settlement_basis;
                $original = $last->original_commission;
                $amount = abs((float) $last->usd_amount);
                $date = $basis === 'unreleased' ? $last->effective_date->toDateString() : now()->toDateString();
            } else {
                $release = CommissionRelease::latestFor($payment->id, (int) $agentId);
                $basis = $release->status;
                $original = $snapshot;
                $original['settlement_breakdown'] = $basis === 'released'
                    ? ($release->commission_breakdown ?? $snapshot['paid_breakdown'] ?? $snapshot)
                    : collect($paidRows->get($agentId) ?? $snapshot['paid_breakdown'] ?? $snapshot)
                        ->except(['activity', 'agent', 'commission_profile', 'paid_breakdown'])->all();
                $amount = abs((float) ($original['settlement_breakdown']['usd_total'] ?? $snapshot['paid_usd_total'] ?? $snapshot['usd_total']));
                $date = $basis === 'unreleased' ? $snapshot['original_sold_date'] : now()->toDateString();
            }

            CommissionAdjustment::create([
                'sales_payment_id' => $payment->id,
                'brand_id' => $activity->brand_id,
                'agent_id' => $agentId,
                'kind' => $kind,
                'effective_date' => $date,
                'settlement_basis' => $basis,
                'usd_amount' => round($amount * ($kind === 'dispute' ? -1 : 1), 2),
                'original_commission' => $original,
            ]);
        }
    }
}
