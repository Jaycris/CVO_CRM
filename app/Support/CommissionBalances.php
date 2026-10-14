<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\CommissionAdjustment;
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
            $earned = SalesMtdCalculator::statementRows($history->get($key, collect()), SalesMtdCalculator::agentTargetRows($targets->get($key, collect())))
                ->groupBy('agent_id')->map(fn ($items) => $items->sum(fn ($row) => (float) $row['exchange_rate'] > 0
                    ? (float) $row['net_commission'] / (float) $row['exchange_rate'] : (float) $row['usd_total']));
            $events = $eventsByMonth->get($key, collect())->groupBy('agent_id')->map->sum('usd_amount');

            foreach ($agentIds as $id) {
                $balances[$id] = self::closingBalance($balances[$id] ?? 0, (float) $earned->get($id, 0), (float) $events->get($id, 0));
            }
        }

        foreach ($adjustments->groupBy('agent_id') as $id => $entries) {
            if (($balances[$id] ?? 0) < 0) {
                $rows->push(self::adjustmentRow($entries->first(), $start, $balances[$id], 'Negative balance brought forward'));
            }
        }

        foreach ($eventsByMonth->get($start->format('Y-m'), collect()) as $entry) {
            $rows->push(self::adjustmentRow($entry, $entry->effective_date, (float) $entry->usd_amount,
                $entry->kind === 'dispute' ? 'Dispute commission deduction' : 'Dispute commission restored'));
        }

        return $rows->values();
    }

    public static function closingBalance(float $opening, float $earned, float $adjustments): float
    {
        // Positive monthly commission is payable; only unpaid debt carries forward.
        return min(0, round($opening + $earned + $adjustments, 2));
    }

    private static function adjustmentRow(CommissionAdjustment $entry, CarbonInterface $date, float $amount, string $label): array
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

        return $row + [
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
    }
}
