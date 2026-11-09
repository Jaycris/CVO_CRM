<?php

namespace App\Support;

use App\Models\CommissionAdjustment;
use App\Models\CommissionRelease;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommissionRefunds
{
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
        return $activities->filter(function ($activity) use ($last, $agentId) {
            foreach (self::shares($activity) as $id) {
                if ((! $agentId || (int) $id === $agentId) && ! $last->get($activity->sales_payment_id.':'.$id)?->isDeduction()) return true;
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
            CommissionDisputes::transition($payment, 'Refund', $refundedOn);
            $new = CommissionAdjustment::where('sales_payment_id', $paymentId)->whereNotIn('id', $before)->get();
            foreach ($new as $entry) {
                $original = $entry->original_commission;
                $original['refund_reconciliation'] = ['reason' => $reason, 'created_by' => auth()->id(),
                    'refunded_on' => $refundedOn?->toDateString(), 'recorded_at' => now()->toIso8601String()];
                $entry->update(['original_commission' => $original]);
            }
            return $new->isNotEmpty();
        });
    }

    private static function shares(SalesActivity $activity): array
    {
        return array_values(array_unique(array_filter([$activity->agent_id,
            (float) $activity->frankie_credit_amount > 0 ? $activity->frankie_agent_id : null])));
    }
}
