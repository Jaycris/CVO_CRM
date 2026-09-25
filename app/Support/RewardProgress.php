<?php

namespace App\Support;

use App\Models\Reward;
use App\Models\RewardUnlock;
use App\Models\ProductionTask;
use App\Models\SalesActivity;
use App\Models\User;
use App\Notifications\RewardUnlockedAdminNotification;
use App\Notifications\RewardUnlockedNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class RewardProgress
{
    public static function currentPeriodMonth(): string
    {
        return now()->startOfMonth()->toDateString();
    }

    public static function visibleFor(User $user, array $summary, ?int $limit = null): Collection
    {
        $individualMtd = (float) data_get($summary, "agentCredits.{$user->id}.mtd", 0);
        $teamMtd = (float) data_get($summary, 'global.mtd', 0);
        $periodMonth = self::currentPeriodMonth();

        $query = Reward::query()
            ->with(['media', 'unlocks' => fn ($query) => $query
                ->where('user_id', $user->id)
                ->whereDate('period_month', $periodMonth)])
            ->where('is_active', true)
            ->when($user->role?->name !== 'Admin', function ($query) use ($user) {
                $query->where('audience', Reward::AUDIENCE_ALL);

                if ($user->is_commission_eligible) {
                    $query->orWhere('audience', Reward::AUDIENCE_COMMISSION_ELIGIBLE);
                }

                if ($user->department === 'Lead Generation') {
                    $query->orWhere('audience', Reward::AUDIENCE_LEAD_GENERATION);
                }

                if ($user->department === 'Production') {
                    $query->orWhere('audience', Reward::AUDIENCE_PRODUCTION);
                }
            })
            ->latest();

        if ($limit) {
            $query->take($limit);
        }

        return $query->get()
            ->map(function (Reward $reward) use ($user, $individualMtd, $teamMtd) {
                return self::attachProgress($reward, $user, $individualMtd, $teamMtd);
            });
    }

    public static function attachProgress(Reward $reward, User $user, float $individualMtd, float $teamMtd): Reward
    {
        $periodMonth = self::currentPeriodMonth();
        $progressAmount = self::progressAmount($reward, $user, $individualMtd, $teamMtd);
        $requirementAmount = max((float) $reward->requirement_amount, 0);
        $progressPercent = $requirementAmount > 0
            ? min(($progressAmount / $requirementAmount) * 100, 100)
            : 100;
        $isUnlocked = $progressAmount >= $requirementAmount;
        $unlock = $reward->relationLoaded('unlocks')
            ? $reward->unlocks->first(fn (RewardUnlock $unlock) => (int) $unlock->user_id === (int) $user->id
                && $unlock->period_month?->toDateString() === $periodMonth)
            : RewardUnlock::query()
                ->where('reward_id', $reward->id)
                ->where('user_id', $user->id)
                ->whereDate('period_month', $periodMonth)
                ->first();

        if ($isUnlocked && ! $unlock) {
            $unlock = RewardUnlock::create([
                'reward_id' => $reward->id,
                'user_id' => $user->id,
                'period_month' => $periodMonth,
                'progress_amount' => $progressAmount,
                'unlocked_at' => now(),
                'expires_at' => $reward->expires_in_days ? now()->addDays($reward->expires_in_days) : null,
            ]);

            $user->notify(new RewardUnlockedNotification($reward));
            self::notifyAdmins($reward, $user);
        } elseif ($unlock && $unlock->progress_amount < $progressAmount) {
            $unlock->forceFill(['progress_amount' => $progressAmount])->save();
        }

        $reward->setAttribute('progress_amount', $progressAmount);
        $reward->setAttribute('progress_percent', $progressPercent);
        $reward->setAttribute('progress_label', self::formatAmount($reward, $progressAmount));
        $reward->setAttribute('requirement_label', self::formatAmount($reward, $requirementAmount));
        $reward->setAttribute('progress_title', self::progressTitle($reward));
        $reward->setAttribute('is_unlocked', $isUnlocked || (bool) $unlock);
        $reward->setAttribute('user_unlock', $unlock);
        $reward->setAttribute('same_tier_claim', $unlock?->claim_requested_at
            ? null
            : self::sameTierClaim($reward, $user));

        return $reward;
    }

    public static function sameTierClaim(Reward $reward, User $user): ?RewardUnlock
    {
        $periodMonth = self::currentPeriodMonth();

        return RewardUnlock::query()
            ->with('reward')
            ->where('user_id', $user->id)
            ->where('reward_id', '!=', $reward->id)
            ->whereDate('period_month', $periodMonth)
            ->whereNotNull('claim_requested_at')
            ->whereHas('reward', function ($query) use ($reward) {
                $query->where('reward_scope', $reward->reward_scope)
                    ->where('audience', $reward->audience)
                    ->where('requirement_type', $reward->requirement_type ?? Reward::REQUIREMENT_SALES_MTD)
                    ->where('requirement_amount', $reward->requirement_amount);
            })
            ->latest('claim_requested_at')
            ->first();
    }

    public static function formatAmount(Reward $reward, float $amount): string
    {
        if ($reward->formatsRequirementAsMoney()) {
            return '$'.number_format($amount, 2);
        }

        return number_format($amount, $amount === floor($amount) ? 0 : 2).' '.$reward->requirementUnitLabel();
    }

    public static function progressTitle(Reward $reward): string
    {
        return match ($reward->reward_scope) {
            Reward::SCOPE_COMPANY => 'Whole team progress',
            Reward::SCOPE_TEAM => 'Team progress',
            default => match ($reward->requirement_type) {
                Reward::REQUIREMENT_SOLD_MINED_LEADS => 'Your sold mined leads',
                Reward::REQUIREMENT_SOLD_VERIFIED_LEADS => 'Your sold verified leads',
                Reward::REQUIREMENT_COMPLETED_PRODUCTION_TASKS => 'Your completed tasks',
                default => 'Your progress',
            },
        };
    }

    private static function progressAmount(Reward $reward, User $user, float $individualMtd, float $globalMtd): float
    {
        return match ($reward->requirement_type ?? Reward::REQUIREMENT_SALES_MTD) {
            Reward::REQUIREMENT_SOLD_MINED_LEADS => self::monthlySoldLeadCount($reward, $user, 'lead_miner_id'),
            Reward::REQUIREMENT_SOLD_VERIFIED_LEADS => self::monthlySoldLeadCount($reward, $user, 'verifier_id'),
            Reward::REQUIREMENT_COMPLETED_PRODUCTION_TASKS => self::monthlyCompletedProductionTaskCount($reward, $user),
            default => match ($reward->reward_scope) {
                Reward::SCOPE_COMPANY => $globalMtd,
                Reward::SCOPE_TEAM => self::monthlyTeamSalesMtd($user),
                default => $individualMtd,
            },
        };
    }

    private static function monthlySoldLeadCount(Reward $reward, User $user, string $creditColumn): float
    {
        $query = SalesActivity::query()
            ->where('payment_status', 'Payment Success')
            ->whereBetween('sold_date', self::monthRange());

        BrandScope::apply($query, $user);
        self::applyUserScope($query, $reward, $user, $creditColumn);

        return (float) $query->count();
    }

    private static function monthlyCompletedProductionTaskCount(Reward $reward, User $user): float
    {
        $query = ProductionTask::query()
            ->where('status', 'fulfilled')
            ->whereBetween('completed_at', self::monthRangeWithTime())
            ->whereHas('project', fn (Builder $query) => BrandScope::apply($query, $user));

        self::applyUserScope($query, $reward, $user, 'assigned_to');

        return (float) $query->count();
    }

    private static function monthlyTeamSalesMtd(User $user): float
    {
        if (! $user->team_id) {
            return 0;
        }

        $query = SalesActivity::query()
            ->with(['agent', 'frankieAgent'])
            ->where('payment_status', 'Payment Success')
            ->whereBetween('sold_date', self::monthRange())
            ->where(function ($query) use ($user) {
                $query->whereHas('agent', fn ($query) => $query->where('team_id', $user->team_id))
                    ->orWhereHas('frankieAgent', fn ($query) => $query->where('team_id', $user->team_id));
            });

        BrandScope::apply($query, $user);

        return (float) $query->get()->sum(function (SalesActivity $activity) use ($user) {
            $amount = 0;

            if ($activity->agent?->team_id === $user->team_id) {
                $amount += (float) ($activity->agent_credit_amount ?: $activity->amount);
            }

            if ($activity->frankieAgent?->team_id === $user->team_id) {
                $amount += (float) $activity->frankie_credit_amount;
            }

            return $amount;
        });
    }

    private static function applyUserScope(Builder $query, Reward $reward, User $user, string $column): void
    {
        if ($reward->reward_scope === Reward::SCOPE_COMPANY) {
            return;
        }

        if ($reward->reward_scope === Reward::SCOPE_TEAM) {
            if (! $user->team_id) {
                $query->whereRaw('1 = 0');

                return;
            }

            $query->whereHas($column === 'assigned_to' ? 'assignedUser' : str($column)->before('_id')->camel()->toString(), function ($query) use ($user) {
                $query->where('team_id', $user->team_id);
            });

            return;
        }

        $query->where($column, $user->id);
    }

    private static function monthRange(): array
    {
        return [
            now()->startOfMonth()->toDateString(),
            now()->endOfMonth()->toDateString(),
        ];
    }

    private static function monthRangeWithTime(): array
    {
        return [
            now()->startOfMonth(),
            now()->endOfMonth(),
        ];
    }

    private static function notifyAdmins(Reward $reward, User $user): void
    {
        self::rewardManagers()
            ->each(fn (User $admin) => $admin->notify(new RewardUnlockedAdminNotification($reward, $user)));
    }

    public static function rewardManagers(): Collection
    {
        return User::query()
            ->with(['role.permissionRecords', 'permissionOverrides'])
            ->get()
            ->filter(fn (User $user) => $user->role?->name === 'Admin' || $user->hasPermission('manage_rewards'))
            ->unique('id')
            ->values();
    }
}
