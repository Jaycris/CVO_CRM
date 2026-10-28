<?php

namespace App\Support;

use App\Models\SalesActivity;
use App\Models\SalesMtdDeduction;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SalesMtdDeductions
{
    public static function entries(CarbonInterface $month, ?User $viewer = null, ?int $brandId = null, ?int $agentId = null, bool $salesBrandsOnly = false, bool $includeOwnAcrossBrands = false): Collection
    {
        if (! Schema::hasTable('sales_mtd_deductions')) {
            return collect();
        }

        return SalesMtdDeduction::with(['agent.commissionProfile.rules', 'brand'])
            ->whereDate('effective_month', $month->copy()->startOfMonth()->toDateString())->whereNull('voided_at')
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when($agentId, fn ($query) => $query->where('agent_id', $agentId))
            ->when($salesBrandsOnly && ! $brandId, fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true)))
            ->when($viewer && ! $brandId, function ($query) use ($viewer, $includeOwnAcrossBrands) {
                $query->where(function ($query) use ($viewer, $includeOwnAcrossBrands) {
                    BrandScope::apply($query, $viewer);
                    if ($includeOwnAcrossBrands) {
                        $query->orWhere('agent_id', $viewer->id);
                    }
                });
            })->orderBy('id')->get();
    }

    public static function groupKey($brandId, $agentId, string $month): string
    {
        return ($brandId ?? 0) . ':' . $agentId . ':' . $month;
    }

    public static function allocations(Collection $entries): array
    {
        if ($entries->isEmpty()) {
            return ['rows' => [], 'excess' => []];
        }

        $groups = $entries->groupBy(fn ($entry) => self::groupKey($entry->brand_id, $entry->agent_id, $entry->effective_month->format('Y-m')));
        $activities = SalesActivity::query()->where('payment_status', 'Payment Success')
            ->whereDate('sold_date', '>=', $entries->min('effective_month')->copy()->startOfMonth()->toDateString())
            ->whereDate('sold_date', '<=', $entries->max('effective_month')->copy()->endOfMonth()->toDateString())
            ->where(fn ($query) => $query->whereIn('agent_id', $entries->pluck('agent_id'))->orWhereIn('frankie_agent_id', $entries->pluck('agent_id')))
            ->orderBy('sold_date')->orderBy('id')->get();
        $credits = [];
        foreach ($activities as $activity) {
            foreach ([[$activity->agent_id, (float) ($activity->agent_credit_amount ?: $activity->amount)],
                [$activity->frankie_agent_id, (float) $activity->frankie_credit_amount]] as [$agentId, $amount]) {
                $key = self::groupKey($activity->brand_id, $agentId, $activity->sold_date->format('Y-m'));
                if ($agentId && $amount > 0 && $groups->has($key)) {
                    $credits[$key][] = ['key' => $activity->id . ':' . $agentId, 'cents' => (int) round($amount * 100)];
                }
            }
        }

        $result = ['rows' => [], 'excess' => []];
        foreach ($groups as $key => $deductions) {
            $rows = $credits[$key] ?? [];
            $gross = array_sum(array_column($rows, 'cents'));
            $deduction = (int) round($deductions->sum('amount') * 100);
            $applied = min($gross, $deduction);
            $cumulative = $allocated = 0;
            // Cumulative cent allocation is stable across separately requested payroll ranges.
            foreach ($rows as $row) {
                $cumulative += $row['cents'];
                $next = (int) round($applied * ($cumulative / $gross));
                $result['rows'][$row['key']] = ($next - $allocated) / 100;
                $allocated = $next;
            }
            if ($deduction > $gross) {
                $result['excess'][] = ['entry' => $deductions->first(), 'amount' => ($deduction - $gross) / 100];
            }
        }

        return $result;
    }
}
