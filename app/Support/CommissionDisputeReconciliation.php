<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\CommissionAdjustmentReconciliation;
use App\Models\CommissionRelease;
use App\Models\CommissionDisputeOffset;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionDisputeReconciliation
{
    public function reconcile(int $adjustmentId, string $disputedAt, string $reason, User $actor): bool
    {
        abort_unless($actor->role?->name === 'Admin', 403);

        return DB::transaction(function () use ($adjustmentId, $disputedAt, $reason, $actor) {
            $entry = CommissionAdjustment::findOrFail($adjustmentId);
            User::withTrashed()->whereKey($entry->agent_id)->lockForUpdate()->firstOrFail();
            // Use the same payment lock as status transitions to prevent double corrections or a concurrent restoration.
            $payment = SalesPayment::query()->tap(fn ($query) => BrandScope::apply($query, $actor))
                ->whereKey($entry->sales_payment_id)->lockForUpdate()->firstOrFail();
            $entry->refresh();
            $latest = CommissionAdjustment::where('sales_payment_id', $payment->id)->where('agent_id', $entry->agent_id)->latest('id')->first();
            if ($payment->status !== 'Dispute' || $entry->kind !== 'dispute' || $latest?->id !== $entry->id) {
                $this->fail('Only the active deduction of a currently disputed payment can be reconciled.');
            }
            if (CommissionAdjustmentReconciliation::where('commission_adjustment_id', $entry->id)->exists()) return false;
            if ($entry->settlement_basis !== null) $this->fail('This deduction already uses release-aware processing and is not a legacy dispute.');

            $activity = SalesActivity::where('sales_payment_id', $payment->id)->firstOrFail();
            if (! $activity->commission_snapshot || ! array_key_exists($entry->agent_id, $activity->commission_snapshot)) {
                $this->fail('The original commission snapshot is missing. Review this payment before reconciliation.');
            }
            if ($disputedAt < $activity->sold_date->toDateString() || $disputedAt > now()->toDateString()) {
                $this->fail('The dispute date must be between the original sale date and today.');
            }
            $covered = (float) CommissionDisputeOffset::where('commission_adjustment_id', $entry->id)->whereNull('voided_at')->sum('usd_amount');
            if ($covered > 0 && $disputedAt !== $entry->effective_date->toDateString()) {
                $this->fail('This deduction already has unpaid-commission offsets. Its date requires a separate review of those offsets before it can be changed.');
            }

            app(PhremsCommissionReleases::class)->syncForActivity($activity);
            $release = CommissionRelease::latestFor($payment->id, $entry->agent_id);
            if ($release?->source !== 'phrems' || $release->status !== 'released'
                || ! is_numeric($release->commission_breakdown['usd_total'] ?? null)) {
                $this->fail('Reconciliation requires a uniquely matched Released commission from PHREMS. Unknown or Unreleased records cannot use this action.');
            }
            if (! $release->released_at || $release->released_at->toDateString() > $disputedAt) {
                $this->fail('The confirmed release must be on or before the actual dispute date.');
            }
            if ($covered > round((float) $release->commission_breakdown['usd_total'], 2)) {
                $this->fail('Existing offsets exceed the verified release amount. Review those allocations before reconciling this deduction.');
            }

            $before = $this->state($entry);
            $original = $entry->original_commission;
            $original['settlement_breakdown'] = $release->commission_breakdown;
            $entry->update([
                'usd_amount' => -round((float) $release->commission_breakdown['usd_total'], 2),
                'settlement_basis' => 'released', 'effective_date' => $disputedAt, 'original_commission' => $original,
            ]);
            CommissionAdjustmentReconciliation::create([
                'commission_adjustment_id' => $entry->id, 'commission_release_id' => $release->id, 'created_by' => $actor->id,
                'before_state' => $before, 'after_state' => $this->state($entry), 'reason' => $reason,
            ]);
            app(CommissionUnreleasedOffsets::class)->settleForAgent(User::withTrashed()->findOrFail($entry->agent_id));
            return true;
        });
    }

    private function state(CommissionAdjustment $entry): array
    {
        return ['usd_amount' => (float) $entry->usd_amount, 'effective_date' => $entry->effective_date->toDateString(),
            'settlement_basis' => $entry->settlement_basis, 'original_commission' => $entry->original_commission];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['reconciliation' => $message]);
    }
}
