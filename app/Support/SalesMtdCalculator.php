<?php

namespace App\Support;

use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class SalesMtdCalculator
{
    public static function summary(?User $user, CarbonInterface $month, ?int $brandId = null): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $activities = SalesActivity::query()
            ->with(['agent', 'frankieAgent', 'service'])
            ->where('payment_status', 'Payment Success')
            ->whereBetween('sold_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when(! $brandId && $user, fn ($query) => BrandScope::apply($query, $user))
            ->get();

        $targets = SalesTarget::query()
            ->whereDate('target_month', $monthStart->toDateString())
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when(! $brandId && $user, fn ($query) => BrandScope::apply($query, $user))
            ->get();

        $agentTargetRows = $targets
            ->where('target_type', 'agent')
            ->whereNotNull('user_id')
            ->keyBy('user_id');

        $creditRows = self::creditRows($activities);
        $agentCredits = $creditRows
            ->groupBy('agent_id')
            ->map(fn (Collection $rows) => [
                'mtd' => (float) $rows->sum('amount'),
                'service_mtd' => (float) $rows->sum('service_amount'),
                'markup_mtd' => (float) $rows->sum('markup_amount'),
                'service_commission' => (float) $rows->sum('service_commission'),
                'markup_commission' => (float) $rows->sum('markup_commission'),
                'usd_total' => (float) $rows->sum('usd_total'),
            ]);

        $globalMtd = $creditRows->sum('amount');
        $globalTarget = (float) $targets->where('target_type', 'global')->sum('amount');
        $remoteTarget = (float) $targets->where('target_type', 'remote')->sum('amount');
        $siteTarget = (float) $targets->where('target_type', 'site')->sum('amount');

        $remoteMtd = self::teamMtd($creditRows, $agentTargetRows, 'remote');
        $siteMtd = self::teamMtd($creditRows, $agentTargetRows, 'site');

        return [
            'month' => $monthStart,
            'global' => self::bucket($globalMtd, $globalTarget),
            'remote' => self::bucket($remoteMtd, $remoteTarget),
            'site' => self::bucket($siteMtd, $siteTarget),
            'agentCredits' => $agentCredits,
            'agentTargets' => $agentTargetRows,
        ];
    }

    private static function creditRows(Collection $activities): Collection
    {
        return $activities->flatMap(function (SalesActivity $activity) {
            $rows = collect();

            if ($activity->agent_id) {
                $rows->push(self::creditRow(
                    $activity,
                    $activity->agent,
                    (float) ($activity->agent_credit_amount ?: $activity->amount)
                ));
            }

            if ($activity->frankie_agent_id && (float) $activity->frankie_credit_amount > 0) {
                $rows->push(self::creditRow(
                    $activity,
                    $activity->frankieAgent,
                    (float) $activity->frankie_credit_amount
                ));
            }

            return $rows;
        });
    }

    private static function creditRow(SalesActivity $activity, ?User $agent, float $creditAmount): array
    {
        $saleAmount = max((float) $activity->amount, 0);
        $shareRatio = $saleAmount > 0 ? min($creditAmount / $saleAmount, 1) : 0;
        $servicePrice = max((float) ($activity->service?->price ?? 0), 0);
        $serviceBase = $servicePrice > 0 ? min($servicePrice, $saleAmount) : $saleAmount;
        $markup = max($saleAmount - $serviceBase, 0);

        $serviceAmount = round($serviceBase * $shareRatio, 2);
        $markupAmount = round($markup * $shareRatio, 2);
        $serviceRate = (float) ($agent?->service_commission_percent ?? 20);
        $markupRate = (float) ($agent?->markup_commission_percent ?? 50);
        $serviceCommission = round($serviceAmount * ($serviceRate / 100), 2);
        $markupCommission = round($markupAmount * ($markupRate / 100), 2);

        return [
            'agent_id' => $agent?->id ?? $activity->agent_id,
            'amount' => $creditAmount,
            'service_amount' => $serviceAmount,
            'markup_amount' => $markupAmount,
            'service_commission' => $serviceCommission,
            'markup_commission' => $markupCommission,
            'usd_total' => $serviceCommission + $markupCommission,
        ];
    }

    private static function teamMtd(Collection $creditRows, Collection $agentTargetRows, string $workSetup): float
    {
        return (float) $creditRows
            ->filter(fn (array $row) => ($agentTargetRows->get($row['agent_id'])?->work_setup) === $workSetup)
            ->sum('amount');
    }

    private static function bucket(float $mtd, float $target): array
    {
        return [
            'mtd' => $mtd,
            'target' => $target,
            'remaining' => max($target - $mtd, 0),
            'percent' => $target > 0 ? round(($mtd / $target) * 100, 2) : 0,
        ];
    }
}
