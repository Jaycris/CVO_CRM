<?php

namespace App\Support;

use App\Models\Reward;
use App\Models\RewardUnlock;
use App\Models\User;
use App\Notifications\RewardUnlockedAdminNotification;
use App\Notifications\RewardUnlockedNotification;
use Illuminate\Support\Collection;

class RewardProgress
{
    public static function visibleFor(User $user, array $summary, ?int $limit = null): Collection
    {
        $individualMtd = (float) data_get($summary, "agentCredits.{$user->id}.mtd", 0);
        $teamMtd = (float) data_get($summary, 'global.mtd', 0);

        $query = Reward::query()
            ->with(['media', 'unlocks' => fn ($query) => $query->where('user_id', $user->id)])
            ->where('is_active', true)
            ->when(! $user->is_commission_eligible, function ($query) {
                $query->where('audience', Reward::AUDIENCE_ALL);
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
        $progressAmount = $reward->reward_scope === Reward::SCOPE_TEAM
            ? $teamMtd
            : $individualMtd;
        $requirementAmount = max((float) $reward->requirement_amount, 0);
        $progressPercent = $requirementAmount > 0
            ? min(($progressAmount / $requirementAmount) * 100, 100)
            : 100;
        $isUnlocked = $progressAmount >= $requirementAmount;
        $unlock = $reward->relationLoaded('unlocks')
            ? $reward->unlocks->firstWhere('user_id', $user->id)
            : RewardUnlock::query()
                ->where('reward_id', $reward->id)
                ->where('user_id', $user->id)
                ->first();

        if ($isUnlocked && ! $unlock) {
            $unlock = RewardUnlock::create([
                'reward_id' => $reward->id,
                'user_id' => $user->id,
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
        $reward->setAttribute('is_unlocked', $isUnlocked || (bool) $unlock);
        $reward->setAttribute('user_unlock', $unlock);
        $reward->setAttribute('same_tier_claim', $unlock?->claim_requested_at
            ? null
            : self::sameTierClaim($reward, $user));

        return $reward;
    }

    public static function sameTierClaim(Reward $reward, User $user): ?RewardUnlock
    {
        return RewardUnlock::query()
            ->with('reward')
            ->where('user_id', $user->id)
            ->where('reward_id', '!=', $reward->id)
            ->whereNotNull('claim_requested_at')
            ->whereHas('reward', function ($query) use ($reward) {
                $query->where('reward_scope', $reward->reward_scope)
                    ->where('requirement_amount', $reward->requirement_amount);
            })
            ->latest('claim_requested_at')
            ->first();
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
