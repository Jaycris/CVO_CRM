<?php

namespace App\Support;

use App\Models\CommissionProfile;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SalesMtdCalculator
{
    public const SERVICE_RATE_LOW = 15.0;
    public const SERVICE_RATE_MID = 20.0;
    public const SERVICE_RATE_HIGH = 25.0;
    public const DEFAULT_SERVICE_THRESHOLD = 500.0;

    private static ?bool $commissionProfilesEnabled = null;

    public static function summary(?User $user, CarbonInterface $month, ?int $brandId = null): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $activityRelations = ['service'];

        if (self::commissionProfilesEnabled()) {
            $activityRelations[] = 'agent.commissionProfile.rules';
            $activityRelations[] = 'frankieAgent.commissionProfile.rules';
        } else {
            $activityRelations[] = 'agent';
            $activityRelations[] = 'frankieAgent';
        }

        $activities = SalesActivity::query()
            ->with($activityRelations)
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

        $creditRows = self::statementRows($activities, $agentTargetRows);
        $agentCredits = $creditRows
            ->groupBy('agent_id')
            ->map(fn (Collection $rows) => [
                'mtd' => (float) $rows->sum('amount'),
                'service_mtd' => (float) $rows->sum('service_amount'),
                'commissionable_service_mtd' => (float) $rows->sum('commissionable_service_amount'),
                'commissionable_markup_mtd' => (float) $rows->sum('commissionable_markup_amount'),
                'threshold_applied_amount' => (float) $rows->sum('threshold_applied_amount'),
                'threshold_applied_service_amount' => (float) $rows->sum('threshold_applied_service_amount'),
                'threshold_applied_markup_amount' => (float) $rows->sum('threshold_applied_markup_amount'),
                'markup_mtd' => (float) $rows->sum('markup_amount'),
                'service_commission' => (float) $rows->sum('service_commission'),
                'markup_commission' => (float) $rows->sum('markup_commission'),
                'usd_total' => (float) $rows->sum('usd_total'),
                'service_commission_percent' => (float) ($rows->last()['service_commission_percent'] ?? self::SERVICE_RATE_LOW),
                'markup_commission_percent' => (float) ($rows->last()['markup_commission_percent'] ?? 50),
                'commission_profile_name' => (string) ($rows->last()['commission_profile_name'] ?? 'Default Service Tiers'),
            ]);

        $globalMtd = $creditRows->sum('amount');
        $globalTarget = (float) $targets->where('target_type', 'global')->sum('amount');
        $remoteTarget = (float) $targets->where('target_type', 'remote')->sum('amount');
        $siteTarget = (float) $targets->where('target_type', 'site')->sum('amount');

        $remoteMtd = self::teamMtd($creditRows, 'remote');
        $siteMtd = self::teamMtd($creditRows, 'site');

        return [
            'month' => $monthStart,
            'global' => self::bucket($globalMtd, $globalTarget),
            'remote' => self::bucket($remoteMtd, $remoteTarget),
            'site' => self::bucket($siteMtd, $siteTarget),
            'agentCredits' => $agentCredits,
            'agentTargets' => $agentTargetRows,
        ];
    }

    public static function statementRows(Collection $activities, ?Collection $agentTargetRows = null): Collection
    {
        $rows = $activities->flatMap(function (SalesActivity $activity) {
            $rows = collect();

            if ($activity->agent_id) {
                $rows->push(self::creditRow(
                    $activity,
                    $activity->agent,
                    (float) ($activity->agent_credit_amount ?: $activity->amount),
                    'Main Agent'
                ));
            }

            if ($activity->frankie_agent_id && (float) $activity->frankie_credit_amount > 0) {
                $rows->push(self::creditRow(
                    $activity,
                    $activity->frankieAgent,
                    (float) $activity->frankie_credit_amount,
                    'Frankie Agent'
                ));
            }

            return $rows;
        });

        return self::applyCommissionProfiles($rows, $agentTargetRows ?? collect());
    }

    private static function creditRow(SalesActivity $activity, ?User $agent, float $creditAmount, string $shareRole): array
    {
        $saleAmount = max((float) $activity->amount, 0);
        $shareRatio = $saleAmount > 0 ? min($creditAmount / $saleAmount, 1) : 0;
        $servicePrice = max((float) ($activity->service?->price ?? 0), 0);
        $serviceBase = $servicePrice > 0 ? min($servicePrice, $saleAmount) : $saleAmount;
        $markup = max($saleAmount - $serviceBase, 0);

        $serviceAmount = round($serviceBase * $shareRatio, 2);
        $markupAmount = round($markup * $shareRatio, 2);
        $serviceRate = self::SERVICE_RATE_LOW;
        $markupRate = (float) ($agent?->markup_commission_percent ?? 50);
        $profile = self::commissionProfilesEnabled() ? $agent?->commissionProfile : null;

        return [
            'activity' => $activity,
            'agent' => $agent,
            'agent_id' => $agent?->id ?? $activity->agent_id,
            'share_role' => $shareRole,
            'share_percent' => round($shareRatio * 100, 2),
            'sale_amount' => $saleAmount,
            'amount' => $creditAmount,
            'credit_amount' => $creditAmount,
            'service_amount' => $serviceAmount,
            'commissionable_service_amount' => $serviceAmount,
            'commissionable_markup_amount' => $markupAmount,
            'threshold_applied_amount' => 0,
            'threshold_applied_service_amount' => 0,
            'threshold_applied_markup_amount' => 0,
            'commission_threshold_amount' => self::serviceThreshold($agent),
            'is_commission_threshold_exempt' => (bool) ($agent?->is_commission_threshold_exempt ?? false),
            'markup_amount' => $markupAmount,
            'commission_profile' => $profile,
            'commission_profile_id' => $profile?->id,
            'commission_profile_name' => $profile?->name ?? 'Default Service Tiers',
            'service_commission_percent' => $serviceRate,
            'markup_commission_percent' => $markupRate,
            'service_commission' => 0,
            'markup_commission' => 0,
            'usd_total' => 0,
        ];
    }

    private static function applyCommissionProfiles(Collection $rows, Collection $agentTargetRows): Collection
    {
        return $rows
            ->groupBy('agent_id')
            ->flatMap(function (Collection $agentRows, $agentId) use ($agentTargetRows) {
                $agentRows = $agentRows
                    ->sortBy(fn (array $row) => sprintf(
                        '%s-%010d',
                        $row['activity']->sold_date?->format('Y-m-d') ?? '',
                        $row['activity']->id ?? 0
                    ))
                    ->values();

                $serviceMtd = (float) $agentRows->sum('service_amount');
                $targetAmount = (float) ($agentTargetRows->get($agentId)?->amount ?? 0);
                $serviceRate = self::serviceRateFor($serviceMtd, $targetAmount, $agentRows->first()['commission_profile'] ?? null);
                $remainingThreshold = self::serviceThreshold($agentRows->first()['agent'] ?? null);

                return $agentRows->map(function (array $row) use (&$remainingThreshold, $serviceRate) {
                    $serviceAmount = (float) $row['service_amount'];
                    $markupAmount = (float) $row['markup_amount'];
                    $serviceThresholdApplied = min($serviceAmount, $remainingThreshold);
                    $remainingThreshold = max($remainingThreshold - $serviceThresholdApplied, 0);
                    $markupThresholdApplied = min($markupAmount, $remainingThreshold);
                    $remainingThreshold = max($remainingThreshold - $markupThresholdApplied, 0);

                    $commissionableServiceAmount = max($serviceAmount - $serviceThresholdApplied, 0);
                    $commissionableMarkupAmount = max($markupAmount - $markupThresholdApplied, 0);

                    $markupRate = (float) $row['markup_commission_percent'];
                    $serviceCommission = round($commissionableServiceAmount * ($serviceRate / 100), 2);
                    $markupCommission = round($commissionableMarkupAmount * ($markupRate / 100), 2);

                    $row['commissionable_service_amount'] = $commissionableServiceAmount;
                    $row['commissionable_markup_amount'] = $commissionableMarkupAmount;
                    $row['threshold_applied_amount'] = $serviceThresholdApplied + $markupThresholdApplied;
                    $row['threshold_applied_service_amount'] = $serviceThresholdApplied;
                    $row['threshold_applied_markup_amount'] = $markupThresholdApplied;
                    $row['service_commission_percent'] = $serviceRate;
                    $row['service_commission'] = $serviceCommission;
                    $row['markup_commission'] = $markupCommission;
                    $row['usd_total'] = $serviceCommission + $markupCommission;

                    return $row;
                });
            })
            ->values();
    }

    private static function serviceRateFor(float $serviceMtd, float $targetAmount, ?CommissionProfile $profile = null): float
    {
        if ($targetAmount <= 0) {
            return self::profileFallbackRate($profile);
        }

        $percent = ($serviceMtd / $targetAmount) * 100;
        $rules = $profile?->rules;

        if ($rules && $rules->isNotEmpty()) {
            $matchedRule = $rules
                ->sortByDesc('minimum_mtd_percent')
                ->first(fn ($rule) => $percent >= (float) $rule->minimum_mtd_percent);

            return (float) ($matchedRule?->commission_percent
                ?? $rules->sortBy('minimum_mtd_percent')->first()->commission_percent);
        }

        if ($percent >= 100) {
            return self::SERVICE_RATE_HIGH;
        }

        if ($percent >= 75) {
            return self::SERVICE_RATE_MID;
        }

        return self::SERVICE_RATE_LOW;
    }

    private static function profileFallbackRate(?CommissionProfile $profile): float
    {
        $rules = $profile?->rules;

        if ($rules && $rules->isNotEmpty()) {
            return (float) $rules->sortBy('minimum_mtd_percent')->first()->commission_percent;
        }

        return self::SERVICE_RATE_LOW;
    }

    private static function serviceThreshold(?User $agent): float
    {
        if ((bool) ($agent?->is_commission_threshold_exempt ?? false)) {
            return 0.0;
        }

        return (float) ($agent?->commission_threshold_amount ?? self::DEFAULT_SERVICE_THRESHOLD);
    }

    private static function teamMtd(Collection $creditRows, string $workType): float
    {
        return (float) $creditRows
            ->filter(fn (array $row) => ($row['agent']?->work_type) === $workType)
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

    private static function commissionProfilesEnabled(): bool
    {
        if (self::$commissionProfilesEnabled !== null) {
            return self::$commissionProfilesEnabled;
        }

        try {
            return self::$commissionProfilesEnabled = Schema::hasTable('commission_profiles')
                && Schema::hasTable('commission_profile_rules')
                && Schema::hasColumn('users', 'commission_profile_id');
        } catch (\Throwable) {
            return self::$commissionProfilesEnabled = false;
        }
    }
}
