<?php

namespace App\Support;

use App\Models\AppSetting;
use App\Models\CommissionProfile;
use App\Models\SalesActivity;
use App\Models\SalesTarget;
use App\Models\Service;
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

    public static function summary(?User $user, CarbonInterface $month, ?int $brandId = null, bool $includeUserCreditsAcrossBrands = false): array
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
            ->when(! $brandId && $user, function ($query) use ($user, $includeUserCreditsAcrossBrands) {
                $thisUserId = $user->id;

                if ($includeUserCreditsAcrossBrands) {
                    $query->where(function ($query) use ($user, $thisUserId) {
                        BrandScope::apply($query, $user);
                        $query->orWhere('agent_id', $thisUserId)
                            ->orWhere('frankie_agent_id', $thisUserId);
                    });

                    return;
                }

                BrandScope::apply($query, $user);
            })
            ->get();

        $activityBrandIds = $activities
            ->pluck('brand_id')
            ->filter()
            ->unique()
            ->values();

        $targets = SalesTarget::query()
            ->whereDate('target_month', $monthStart->toDateString())
            ->when($brandId, fn ($query) => $query->where('brand_id', $brandId))
            ->when(! $brandId && $user, function ($query) use ($user, $includeUserCreditsAcrossBrands, $activityBrandIds) {
                if ($includeUserCreditsAcrossBrands) {
                    $query->where(function ($query) use ($user, $activityBrandIds) {
                        BrandScope::apply($query, $user);
                        $query->orWhere('user_id', $user->id);

                        if ($activityBrandIds->isNotEmpty()) {
                            $query->orWhereIn('brand_id', $activityBrandIds->all());
                        }
                    });

                    return;
                }

                BrandScope::apply($query, $user);
            })
            ->get();

        $agentTargetRows = self::agentTargetRows($targets);

        $creditRows = self::statementRows($activities, $agentTargetRows);
        $exchangeRate = AppSetting::commissionExchangeRate();
        $cardHoldPercent = AppSetting::cardPaymentHoldPercent();
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
                'php_total' => (float) $rows->sum('php_total'),
                'exchange_rate' => $exchangeRate,
                'card_payment_hold_percent' => $cardHoldPercent,
                'hold_amount' => (float) $rows->sum('hold_amount'),
                'net_commission' => (float) $rows->sum('net_commission'),
                'service_commission_percent' => (float) ($rows->last()['service_commission_percent'] ?? self::SERVICE_RATE_LOW),
                'markup_commission_percent' => (float) ($rows->last()['markup_commission_percent'] ?? 50),
                'commission_profile_name' => (string) ($rows->last()['commission_profile_name'] ?? 'Default Service Tiers'),
            ]);

        $globalMtd = $creditRows->sum('amount');
        $globalTarget = (float) $targets->where('target_type', 'global')->sum('amount');
        $remoteTarget = (float) $targets->where('target_type', 'remote')->sum('amount');
        $siteTarget = (float) $targets->where('target_type', 'site')->sum('amount');

        $remoteMtd = self::teamMtd($creditRows, 'remote');
        $siteMtd = self::teamMtd($creditRows, 'site') + self::teamMtd($creditRows, 'hybrid');

        return [
            'month' => $monthStart,
            'global' => self::bucket($globalMtd, $globalTarget),
            'remote' => self::bucket($remoteMtd, $remoteTarget),
            'hybrid' => self::bucket(0, 0),
            'site' => self::bucket($siteMtd, $siteTarget),
            'agentCredits' => $agentCredits,
            'agentTargets' => $agentTargetRows,
            'exchangeRate' => $exchangeRate,
            'cardPaymentHoldPercent' => $cardHoldPercent,
        ];
    }

    public static function statementRows(Collection $activities, ?Collection $agentTargetRows = null): Collection
    {
        $activitySplits = self::activityServiceMarkupSplits($activities);

        $rows = $activities->flatMap(function (SalesActivity $activity) use ($activitySplits) {
            $rows = collect();
            $split = $activitySplits[$activity->id] ?? null;

            if ($activity->agent_id) {
                $rows->push(self::creditRow(
                    $activity,
                    $activity->agent,
                    (float) ($activity->agent_credit_amount ?: $activity->amount),
                    'Main Agent',
                    $split
                ));
            }

            if ($activity->frankie_agent_id && (float) $activity->frankie_credit_amount > 0) {
                $rows->push(self::creditRow(
                    $activity,
                    $activity->frankieAgent,
                    (float) $activity->frankie_credit_amount,
                    'Frankie Agent',
                    $split
                ));
            }

            return $rows;
        });

        return self::applyCommissionProfiles($rows, $agentTargetRows ?? collect(), AppSetting::commissionExchangeRate());
    }

    private static function creditRow(SalesActivity $activity, ?User $agent, float $creditAmount, string $shareRole, ?array $activitySplit = null): array
    {
        $saleAmount = max((float) $activity->amount, 0);
        $shareRatio = $saleAmount > 0 ? min($creditAmount / $saleAmount, 1) : 0;
        $serviceBase = (float) ($activitySplit['service_amount'] ?? $saleAmount);
        $markup = (float) ($activitySplit['markup_amount'] ?? 0);

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
            'payment_method' => $activity->payment_method,
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
            'php_total' => 0,
            'exchange_rate' => AppSetting::commissionExchangeRate(),
            'card_payment_hold_percent' => AppSetting::cardPaymentHoldPercent(),
            'hold_amount' => 0,
            'net_commission' => 0,
        ];
    }

    private static function activityServiceMarkupSplits(Collection $activities): array
    {
        $servicePricesByName = self::servicePricesByName($activities);
        $splits = [];

        $activities
            ->groupBy(fn (SalesActivity $activity) => self::salePackageKey($activity))
            ->each(function (Collection $packageActivities) use (&$splits, $servicePricesByName) {
                $orderedActivities = $packageActivities
                    ->sortBy(fn (SalesActivity $activity) => sprintf(
                        '%s-%010d',
                        $activity->sold_date?->format('Y-m-d') ?? '',
                        $activity->id ?? 0
                    ))
                    ->values();
                $firstActivity = $orderedActivities->first();
                $servicePrice = self::servicePriceForActivity($firstActivity, $servicePricesByName);
                $priorPaidAmount = self::priorPackagePaidAmount($firstActivity, $servicePricesByName);
                $remainingService = $servicePrice > 0 ? max($servicePrice - $priorPaidAmount, 0) : null;

                $orderedActivities->each(function (SalesActivity $activity) use (&$splits, &$remainingService) {
                    $saleAmount = max((float) $activity->amount, 0);

                    if ($remainingService === null) {
                        $serviceAmount = $saleAmount;
                    } else {
                        $serviceAmount = min($saleAmount, $remainingService);
                        $remainingService = max($remainingService - $serviceAmount, 0);
                    }

                    $splits[$activity->id] = [
                        'service_amount' => round($serviceAmount, 2),
                        'markup_amount' => round(max($saleAmount - $serviceAmount, 0), 2),
                    ];
                });
            });

        return $splits;
    }

    private static function salePackageKey(SalesActivity $activity): string
    {
        $clientKey = $activity->lead_id
            ? 'lead:' . $activity->lead_id
            : 'client:' . self::normalizeKey($activity->author_name) . '|book:' . self::normalizeKey($activity->book_title);
        $serviceKey = $activity->service_id
            ? 'service:' . $activity->service_id
            : 'service-name:' . self::normalizeKey($activity->service_name);

        return implode('|', [
            'agent:' . ($activity->agent_id ?? 'none'),
            'frankie:' . ($activity->frankie_agent_id ?? 'none'),
            $clientKey,
            $serviceKey,
        ]);
    }

    private static function priorPackagePaidAmount(?SalesActivity $activity, array $servicePricesByName): float
    {
        if (! $activity?->sold_date || self::servicePriceForActivity($activity, $servicePricesByName) <= 0) {
            return 0.0;
        }

        return (float) SalesActivity::query()
            ->where('payment_status', 'Payment Success')
            ->whereDate('sold_date', '<', $activity->sold_date->toDateString())
            ->where('agent_id', $activity->agent_id)
            ->when(
                $activity->frankie_agent_id,
                fn ($query) => $query->where('frankie_agent_id', $activity->frankie_agent_id),
                fn ($query) => $query->whereNull('frankie_agent_id')
            )
            ->when(
                $activity->lead_id,
                fn ($query) => $query->where('lead_id', $activity->lead_id),
                fn ($query) => $query
                    ->whereRaw("LOWER(TRIM(COALESCE(author_name, ''))) = ?", [self::normalizeKey($activity->author_name)])
                    ->whereRaw("LOWER(TRIM(COALESCE(book_title, ''))) = ?", [self::normalizeKey($activity->book_title)])
            )
            ->when(
                $activity->service_id,
                fn ($query) => $query->where('service_id', $activity->service_id),
                fn ($query) => $query->whereRaw("LOWER(TRIM(COALESCE(service_name, ''))) = ?", [self::normalizeKey($activity->service_name)])
            )
            ->sum('amount');
    }

    private static function servicePriceForActivity(?SalesActivity $activity, array $servicePricesByName): float
    {
        if (! $activity) {
            return 0.0;
        }

        $servicePrice = max((float) ($activity->service?->price ?? 0), 0);

        if ($servicePrice > 0) {
            return $servicePrice;
        }

        return (float) ($servicePricesByName[self::normalizeKey($activity->service_name)] ?? 0);
    }

    private static function servicePricesByName(Collection $activities): array
    {
        $names = $activities
            ->pluck('service_name')
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        return Service::query()
            ->whereIn('name', $names->all())
            ->get(['name', 'price'])
            ->mapWithKeys(fn (Service $service) => [self::normalizeKey($service->name) => (float) $service->price])
            ->all();
    }

    private static function normalizeKey(?string $value): string
    {
        return strtolower(trim((string) $value));
    }

    private static function applyCommissionProfiles(Collection $rows, Collection $agentTargetRows, float $exchangeRate): Collection
    {
        return $rows
            ->groupBy('agent_id')
            ->flatMap(function (Collection $agentRows, $agentId) use ($agentTargetRows, $exchangeRate) {
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

                return $agentRows->map(function (array $row) use (&$remainingThreshold, $serviceRate, $exchangeRate) {
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
                    $row['exchange_rate'] = $exchangeRate;
                    $row['php_total'] = round($row['usd_total'] * $exchangeRate, 2);
                    $row['card_payment_hold_percent'] = AppSetting::cardPaymentHoldPercent();
                    $row['hold_amount'] = self::cardPaymentHoldAmount(
                        $row['payment_method'] ?? null,
                        (float) $row['php_total'],
                        (float) $row['card_payment_hold_percent']
                    );
                    $row['net_commission'] = round((float) $row['php_total'] - (float) $row['hold_amount'], 2);

                    return $row;
                });
            })
            ->values();
    }

    public static function agentTargetRows(Collection $targets): Collection
    {
        $agentTargets = $targets
            ->where('target_type', 'agent')
            ->whereNotNull('user_id')
            ->values();

        $userBrandIds = User::query()
            ->whereIn('id', $agentTargets->pluck('user_id')->filter()->unique()->values())
            ->pluck('brand_id', 'id');

        return $agentTargets
            ->groupBy('user_id')
            ->map(function (Collection $rows, $userId) use ($userBrandIds) {
                $currentBrandId = $userBrandIds->get($userId);
                $currentBrandRow = $rows->first(fn (SalesTarget $target) => (int) $target->brand_id === (int) $currentBrandId);

                return $currentBrandRow ?: $rows->sortByDesc('id')->first();
            });
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

    private static function cardPaymentHoldAmount(?string $paymentMethod, float $phpTotal, float $holdPercent): float
    {
        if ($paymentMethod !== 'Card' || $phpTotal <= 0 || $holdPercent <= 0) {
            return 0.0;
        }

        return round($phpTotal * ($holdPercent / 100), 2);
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
