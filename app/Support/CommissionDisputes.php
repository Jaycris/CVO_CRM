<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\SalesTarget;

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

        if (! $snapshots && $status === 'Dispute' && $payment->status === 'Payment Success') {
            $month = $activity->sold_date->copy()->startOfMonth();
            $agentIds = array_filter([$activity->agent_id, $activity->frankie_agent_id]);
            $activities = SalesActivity::with(['agent.commissionProfile.rules', 'frankieAgent.commissionProfile.rules', 'service'])
                ->where('payment_status', 'Payment Success')
                ->whereBetween('sold_date', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])
                ->where(fn ($query) => $query->whereIn('agent_id', $agentIds)->orWhereIn('frankie_agent_id', $agentIds))
                ->get();
            $targets = SalesMtdCalculator::agentTargetRows(SalesTarget::whereDate('target_month', $month)->get());
            $snapshots = SalesMtdCalculator::statementRows($activities, $targets)
                ->filter(fn ($row) => $row['activity']->id === $activity->id)
                ->mapWithKeys(function ($row) {
                    $snapshot = collect($row)->except(['activity', 'agent', 'commission_profile'])->all();
                    $snapshot['author_name'] = $row['activity']->author_name;
                    $snapshot['book_title'] = $row['activity']->book_title;
                    $snapshot['endorsement_code'] = $row['activity']->endorsement_code;
                    $snapshot['original_sold_date'] = $row['activity']->sold_date->toDateString();

                    return [$row['agent_id'] => $snapshot];
                })->all();
            $activity->update(['commission_snapshot' => $snapshots]);
        }

        foreach ($snapshots ?: [] as $agentId => $snapshot) {
            $last = CommissionAdjustment::where('sales_payment_id', $payment->id)
                ->where('agent_id', $agentId)->latest('id')->first();
            $kind = $status === 'Dispute' ? 'dispute' : 'reinstatement';

            if ($last?->kind === $kind || ($kind === 'reinstatement' && ! $last)) {
                continue;
            }

            CommissionAdjustment::create([
                'sales_payment_id' => $payment->id,
                'brand_id' => $activity->brand_id,
                'agent_id' => $agentId,
                'kind' => $kind,
                'effective_date' => now()->toDateString(),
                'usd_amount' => round(abs((float) $snapshot['usd_total']) * ($kind === 'dispute' ? -1 : 1), 2),
                'original_commission' => $snapshot,
            ]);
        }
    }
}
