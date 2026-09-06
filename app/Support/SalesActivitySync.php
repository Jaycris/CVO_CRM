<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\SalesActivity;
use App\Models\SalesPayment;

class SalesActivitySync
{
    public static function sync(SalesPayment $payment): void
    {
        $endorsement = $payment->endorsement;

        if (! $endorsement) {
            return;
        }

        $existingActivity = SalesActivity::where('sales_payment_id', $payment->id)->first();

        if ($payment->status !== 'Payment Success' && ! $existingActivity) {
            return;
        }

        $lead = $endorsement->lead;
        $amount = (float) ($payment->amount ?? $endorsement->amount ?? 0);
        $frankiePercent = ($endorsement->has_frankie && $endorsement->frankie_agent_id)
            ? (float) ($endorsement->frankie_commission_percent ?? AppSetting::get('frankie_commission_percent', 50))
            : 0.0;
        $frankieCredit = round($amount * ($frankiePercent / 100), 2);
        $agentCredit = round($amount - $frankieCredit, 2);

        SalesActivity::updateOrCreate(
            ['sales_payment_id' => $payment->id],
            [
                'brand_id' => $payment->brand_id ?? $endorsement->brand_id,
                'sales_endorsement_id' => $endorsement->id,
                'lead_id' => $endorsement->lead_id,
                'agent_id' => $endorsement->agent_id,
                'frankie_agent_id' => $endorsement->frankie_agent_id,
                'lead_miner_id' => $lead?->created_by,
                'verifier_id' => $lead?->verified_by,
                'service_id' => $endorsement->service_id,
                'activity_type' => 'payment_success',
                'endorsement_code' => $endorsement->endorsement_code,
                'author_name' => $endorsement->author_name,
                'book_title' => $endorsement->book_title,
                'service_name' => $endorsement->service?->name ?? $endorsement->services,
                'amount' => $amount,
                'agent_credit_amount' => $agentCredit,
                'frankie_credit_amount' => $frankieCredit,
                'frankie_commission_percent' => $frankiePercent,
                'payment_method' => $payment->payment_method,
                'payment_status' => $payment->status,
                'sold_date' => $payment->sold_date,
            ]
        );
    }

    /**
     * Drop the activity row for a payment that is leaving the reporting set.
     *
     * Force deletes are handled by the sales_activities.sales_payment_id
     * foreign key (cascadeOnDelete); this covers the soft-delete path, which
     * never reaches that constraint.
     */
    public static function forget(SalesPayment $payment): void
    {
        SalesActivity::where('sales_payment_id', $payment->id)->delete();
    }
}
