<?php

namespace App\Http\Controllers;

use App\Models\Reward;
use App\Models\RewardUnlock;
use App\Models\User;
use App\Notifications\RewardClaimRequestedNotification;
use App\Support\BrandScope;
use App\Support\RewardProgress;
use App\Support\SalesMtdCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RewardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $month = now();
        $summary = SalesMtdCalculator::summary(
            $user,
            $month,
            BrandScope::userBrandId($user),
            $user?->department === 'Sales'
        );

        $individualMtd = (float) data_get($summary, "agentCredits.{$user?->id}.mtd", 0);
        $teamMtd = (float) data_get($summary, 'global.mtd', 0);
        $rewards = RewardProgress::visibleFor($user, $summary);

        return view('rewards.index', compact('rewards', 'individualMtd', 'teamMtd', 'month'));
    }

    public function showClaim(Request $request, Reward $reward): View
    {
        $unlock = $this->unlockedRewardForUser($request, $reward);
        $sameTierClaim = $unlock->claim_requested_at
            ? null
            : RewardProgress::sameTierClaim($reward, $request->user());

        return view('rewards.claim', compact('reward', 'unlock', 'sameTierClaim'));
    }

    public function requestClaim(Request $request, Reward $reward): RedirectResponse
    {
        $unlock = $this->unlockedRewardForUser($request, $reward);

        if ($reward->reward_scope === Reward::SCOPE_COMPANY) {
            return redirect()
                ->route('rewards.claim.show', $reward)
                ->with('success', 'This whole-team reward is unlocked. Admin will announce the details and next steps soon.');
        }

        if ($unlock->claimed_at) {
            return redirect()
                ->route('rewards.claim.show', $reward)
                ->with('success', 'This reward has already been claimed.');
        }

        if ($unlock->expires_at && $unlock->expires_at->isPast()) {
            return redirect()
                ->route('rewards.claim.show', $reward)
                ->with('error', 'This reward claim window has already expired.');
        }

        $alreadyRequested = (bool) $unlock->claim_requested_at;

        if (! $alreadyRequested) {
            $sameTierClaim = RewardProgress::sameTierClaim($reward, $request->user());

            if ($sameTierClaim) {
                return redirect()
                    ->route('rewards.claim.show', $reward)
                    ->with('error', 'You already selected another reward with the same requirement.');
            }

            $unlock->forceFill(['claim_requested_at' => now()])->save();

            RewardProgress::rewardManagers()
                ->each(fn (User $admin) => $admin->notify(new RewardClaimRequestedNotification($unlock)));
        }

        return redirect()
            ->route('rewards.claim.show', $reward)
            ->with('success', $alreadyRequested
                ? 'Your claim request was already sent to Admin.'
                : "Your claim request has been sent to Admin. They'll reach out to you for the information.");
    }

    private function unlockedRewardForUser(Request $request, Reward $reward): RewardUnlock
    {
        $user = $request->user();
        $reward->loadMissing('media');

        abort_unless(
            $reward->is_active
                && $this->userCanSeeReward($user, $reward),
            404
        );

        $summary = SalesMtdCalculator::summary(
            $user,
            now(),
            BrandScope::userBrandId($user),
            $user?->department === 'Sales'
        );

        RewardProgress::attachProgress(
            $reward,
            $user,
            (float) data_get($summary, "agentCredits.{$user->id}.mtd", 0),
            (float) data_get($summary, 'global.mtd', 0),
        );

        $unlock = RewardUnlock::query()
            ->where('reward_id', $reward->id)
            ->where('user_id', $user->id)
            ->whereDate('period_month', RewardProgress::currentPeriodMonth())
            ->first();

        abort_unless($unlock, 403);

        return $unlock->loadMissing('reward.media', 'user');
    }

    private function userCanSeeReward(?User $user, Reward $reward): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role?->name === 'Admin') {
            return true;
        }

        return match ($reward->audience) {
            Reward::AUDIENCE_ALL => true,
            Reward::AUDIENCE_LEAD_GENERATION => $user->department === 'Lead Generation',
            Reward::AUDIENCE_PRODUCTION => $user->department === 'Production',
            default => (bool) $user->is_commission_eligible,
        };
    }
}
