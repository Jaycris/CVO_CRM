<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\CommissionDisputeOffset;
use App\Models\CommissionRelease;
use App\Models\SalesActivity;
use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TrashDeletionGuard
{
    public static function check(Collection $records): void
    {
        if ($records->first() instanceof SalesPayment) {
            self::checkPayments($records->pluck('id')->all());
        } elseif ($records->first() instanceof SalesEndorsement) {
            // Endorsement deletion cascades to payments without running their model hooks.
            self::checkPayments(SalesPayment::withTrashed()->whereIn('sales_endorsement_id', $records->pluck('id'))->pluck('id')->all());
        }
    }

    private static function checkPayments(array $ids): void
    {
        if ($ids === []) return;

        $protected = CommissionRelease::whereIn('sales_payment_id', $ids)->pluck('sales_payment_id')
            ->merge(CommissionDisputeOffset::whereIn('sales_payment_id', $ids)->pluck('sales_payment_id'))
            ->merge(CommissionAdjustment::whereIn('sales_payment_id', $ids)->pluck('sales_payment_id'))
            ->merge(SalesActivity::whereIn('sales_payment_id', $ids)->whereNotNull('commission_snapshot')->pluck('sales_payment_id'))
            ->unique()->values();
        if ($protected->isEmpty()) return;

        $references = SalesPayment::withTrashed()->with(['endorsement' => fn ($query) => $query->withTrashed()])
            ->whereIn('id', $protected)->orderBy('id')->limit(5)->get()
            ->map(fn ($payment) => ($payment->endorsement?->endorsement_code ?: 'Payment').' (#'.$payment->id.')')->implode(', ');
        throw ValidationException::withMessages([
            'deletion' => 'Cannot permanently delete '.$references.' because linked commission release, dispute, or offset history must be preserved. No selected records were deleted. These records remain in Trash and can be restored for review.',
        ]);
    }
}
