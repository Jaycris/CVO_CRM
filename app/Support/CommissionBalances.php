<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\CommissionAdjustment;
use App\Models\CommissionDisputeOffset;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class CommissionBalances
{
    public static function apply(Collection $rows, CarbonInterface $month, ?User $viewer = null, ?int $brandId = null, ?int $agentId = null, bool $salesBrandsOnly = false, bool $includeOwnAcrossBrands = false): Collection
    {
        $start = $month->copy()->startOfMonth();
        $adjustments = CommissionAdjustment::with(['agent', 'brand'])
            ->whereDate('effective_date', '<=', $start->copy()->endOfMonth()->toDateString())
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($agentId, fn ($query) => $query->where('agent_id', $agentId))
            ->when($salesBrandsOnly && ! $brandId, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true)))
            ->when($viewer && ! $brandId && ! BrandScope::canAccessAllBrands($viewer), function ($query) use ($viewer, $includeOwnAcrossBrands) {
                $query->where(function ($query) use ($viewer, $includeOwnAcrossBrands) {
                    BrandScope::apply($query, $viewer);
                    if ($includeOwnAcrossBrands) {
                        $query->orWhere('agent_id', $viewer->id);
                    }
                });
            })
            ->orderBy('effective_date')->orderBy('id')->get();

        $offsetEvents = CommissionDisputeOffset::with('adjustment')->whereNull('voided_at')
            ->whereDate('commission_date', '<=', $start->copy()->endOfMonth()->toDateString())
            ->when($agentId, fn ($query) => $query->where('agent_id', $agentId))->get()->flatMap(function ($offset) {
                $original = $offset->adjustment;
                if (! $original) return [];
                return [
                    new CommissionAdjustment(['brand_id' => $offset->commission_snapshot['brand_id'], 'agent_id' => $offset->agent_id,
                        'effective_date' => $offset->commission_date, 'usd_amount' => -(float) $offset->usd_amount,
                        'kind' => 'offset', 'original_commission' => $offset->commission_snapshot,
                        'display_label' => 'Commission applied to prior dispute']),
                    new CommissionAdjustment(['brand_id' => $original->brand_id, 'agent_id' => $offset->agent_id,
                        'effective_date' => $offset->dispute_date, 'usd_amount' => (float) $offset->usd_amount,
                        'kind' => 'offset', 'original_commission' => $original->original_commission,
                        'display_label' => 'Dispute covered by prior commission']),
                ];
            });
        (new \Illuminate\Database\Eloquent\Collection($offsetEvents->all()))->load(['agent', 'brand']);
        $offsetEvents = $offsetEvents->filter(function ($entry) use ($start, $brandId, $agentId, $viewer, $salesBrandsOnly, $includeOwnAcrossBrands) {
            if ($entry->effective_date->gt($start->copy()->endOfMonth())) return false;
            if ($brandId && (int) $entry->brand_id !== $brandId) return false;
            if ($agentId && (int) $entry->agent_id !== $agentId) return false;
            if ($salesBrandsOnly && ! $brandId && ! $entry->brand?->is_sales_brand) return false;
            return ! $viewer || $brandId || BrandScope::canAccessAllBrands($viewer)
                || (int) $entry->brand_id === (int) $viewer->brand_id || ($includeOwnAcrossBrands && (int) $entry->agent_id === $viewer->id);
        });
        $adjustments = collect($adjustments->all())->concat($offsetEvents)->sortBy(fn ($entry) => $entry->effective_date->toDateString())->values();

        if ($adjustments->isEmpty()) {
            return $rows;
        }

        $earliest = $adjustments->first()->effective_date->copy()->startOfMonth();
        $agentIds = $adjustments->pluck('agent_id')->unique();
        $history = SalesActivity::with(['brand', 'agent.commissionProfile.rules', 'frankieAgent.commissionProfile.rules', 'service'])
            ->where('payment_status', 'Payment Success')
            ->whereDate('sold_date', '>=', $earliest->toDateString())
            ->whereDate('sold_date', '<', $start->toDateString())
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($salesBrandsOnly && ! $brandId, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true)))
            ->when($viewer && ! $brandId && ! BrandScope::canAccessAllBrands($viewer), function ($query) use ($viewer, $includeOwnAcrossBrands) {
                $query->where(function ($query) use ($viewer, $includeOwnAcrossBrands) {
                    BrandScope::apply($query, $viewer);
                    if ($includeOwnAcrossBrands) {
                        $query->orWhere('agent_id', $viewer->id)->orWhere('frankie_agent_id', $viewer->id);
                    }
                });
            })
            ->where(fn ($query) => $query->whereIn('agent_id', $agentIds)->orWhereIn('frankie_agent_id', $agentIds))
            ->get()->groupBy(fn ($activity) => $activity->sold_date->format('Y-m'));
        $targets = SalesTarget::whereBetween('target_month', [$earliest->toDateString(), $start->toDateString()])
            ->where('target_type', 'agent')->whereIn('user_id', $agentIds)->get()
            ->groupBy(fn ($target) => $target->target_month->format('Y-m'));
        $eventsByMonth = $adjustments->groupBy(fn ($entry) => $entry->effective_date->format('Y-m'));
        $balances = [];

        for ($cursor = $earliest->copy(); $cursor->lt($start); $cursor->addMonth()) {
            $key = $cursor->format('Y-m');
            $targetRows = SalesMtdCalculator::agentTargetRows($targets->get($key, collect()));
            $historyRows = SalesMtdCalculator::statementRows($history->get($key, collect()), $targetRows);
            $deductions = SalesMtdDeductions::entries($cursor, $viewer, $brandId, $agentId, $salesBrandsOnly, $includeOwnAcrossBrands);
            $earned = SalesMtdCalculator::withMtdDeductions($historyRows, $targetRows, $deductions)
                ->groupBy('agent_id')->map(fn ($items) => $items->sum(fn ($row) => (float) $row['exchange_rate'] > 0
                    ? (float) $row['net_commission'] / (float) $row['exchange_rate'] : (float) $row['usd_total']));
            $events = $eventsByMonth->get($key, collect())->groupBy('agent_id')->map(fn ($entries) => $entries->sum(function ($entry) {
                $breakdown = $entry->original_commission['settlement_breakdown'] ?? null;
                if ($entry->settlement_basis === 'unreleased' && $breakdown && (float) ($breakdown['exchange_rate'] ?? 0) > 0) {
                    return ((float) $breakdown['net_commission'] / (float) $breakdown['exchange_rate']) * ($entry->isDeduction() ? -1 : 1);
                }
                return (float) $entry->usd_amount;
            }));

            foreach ($agentIds as $id) {
                $balances[$id] = self::closingBalance($balances[$id] ?? 0, (float) $earned->get($id, 0), (float) $events->get($id, 0));
            }
        }

        foreach ($adjustments->groupBy('agent_id') as $id => $entries) {
            if (($balances[$id] ?? 0) < 0) {
                $rows->push(self::adjustmentRow($entries->first(), $start, $balances[$id], 'Negative balance brought forward', false));
            }
        }

        foreach ($eventsByMonth->get($start->format('Y-m'), collect()) as $entry) {
            $rows->push(self::adjustmentRow($entry, $entry->effective_date, (float) $entry->usd_amount,
                $entry->display_label ?? match ($entry->kind) {
                    'dispute' => 'Dispute commission deduction',
                    'refund' => 'Refund commission reversal',
                    'reinstatement' => ($entry->original_commission['cancellation_status'] ?? null) === 'Refund' ? 'Refund commission restored' : 'Dispute commission restored',
                    default => (float) $entry->usd_amount < 0 ? 'Commission adjustment deduction' : 'Commission adjustment credit',
                }));
        }

        return $rows->values();
    }

    public static function closingBalance(float $opening, float $earned, float $adjustments): float
    {
        // Positive monthly commission is payable; only unpaid debt carries forward.
        return min(0, round($opening + $earned + $adjustments, 2));
    }

    private static function adjustmentRow(CommissionAdjustment $entry, CarbonInterface $date, float $amount, string $label, bool $useSettlementBreakdown = true): array
    {
        $rate = AppSetting::commissionExchangeRate();
        $activity = new SalesActivity([
            'brand_id' => $entry->brand_id,
            'agent_id' => $entry->agent_id,
            'activity_type' => 'commission_adjustment',
            'sold_date' => $date,
            'author_name' => $entry->original_commission['author_name'] ?? null,
            'book_title' => $entry->original_commission['book_title'] ?? null,
            'endorsement_code' => $entry->original_commission['endorsement_code'] ?? null,
            'service_name' => $label,
        ]);
        $activity->setRelation('brand', $entry->brand);
        $activity->setRelation('agent', $entry->agent);

        $row = array_fill_keys([
            'sale_amount', 'amount', 'credit_amount', 'share_percent', 'service_amount', 'markup_amount',
            'commissionable_service_amount', 'commissionable_markup_amount', 'threshold_applied_amount',
            'threshold_applied_service_amount', 'threshold_applied_markup_amount', 'commission_threshold_amount',
            'service_commission_percent', 'markup_commission_percent', 'service_commission', 'markup_commission',
            'hold_amount', 'card_payment_hold_percent',
        ], 0);

        $row = $row + [
            'activity' => $activity,
            'agent' => $entry->agent,
            'agent_id' => $entry->agent_id,
            'share_role' => 'Commission adjustment',
            'payment_method' => null,
            'commission_profile_name' => $label,
            'is_commission_threshold_exempt' => true,
            'usd_total' => round($amount, 2),
            'exchange_rate' => $rate,
            'php_total' => round($amount * $rate, 2),
            'net_commission' => round($amount * $rate, 2),
        ];

        // Cancel the unpaid commission and its hold using the original calculation, not today's FX rate.
        if ($useSettlementBreakdown && $entry->settlement_basis === 'unreleased' && ($breakdown = $entry->original_commission['settlement_breakdown'] ?? null)) {
            $sign = $entry->isDeduction() ? -1 : 1;
            foreach (['service_commission', 'markup_commission', 'usd_total', 'php_total', 'hold_amount', 'net_commission'] as $field) {
                $row[$field] = round((float) ($breakdown[$field] ?? 0) * $sign, 2);
            }
            $row['exchange_rate'] = (float) ($breakdown['exchange_rate'] ?? $rate);
            $row['card_payment_hold_percent'] = (float) ($breakdown['card_payment_hold_percent'] ?? 0);
        }

        return $row;
    }
}
