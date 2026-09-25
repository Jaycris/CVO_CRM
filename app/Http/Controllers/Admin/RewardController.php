<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RewardMedia;
use App\Models\RewardUnlock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RewardController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $rewards = Reward::query()
            ->with(['creator', 'media'])
            ->latest()
            ->paginate(\App\Models\AppSetting::recordsPerPage())
            ->withQueryString();

        return view('admin.rewards.index', compact('rewards'));
    }

    public function claims(Request $request): View
    {
        $this->ensureAdmin($request);

        $status = in_array($request->query('status'), ['pending', 'claimed'], true)
            ? $request->query('status')
            : 'pending';

        $claims = RewardUnlock::query()
            ->with(['reward.media', 'user'])
            ->whereNotNull('claim_requested_at')
            ->when(
                $status === 'pending',
                fn ($query) => $query->whereNull('claimed_at'),
                fn ($query) => $query->whereNotNull('claimed_at'),
            )
            ->latest($status === 'pending' ? 'claim_requested_at' : 'claimed_at')
            ->paginate(\App\Models\AppSetting::recordsPerPage())
            ->withQueryString();

        $pendingCount = RewardUnlock::query()
            ->whereNotNull('claim_requested_at')
            ->whereNull('claimed_at')
            ->count();
        $claimedCount = RewardUnlock::query()
            ->whereNotNull('claimed_at')
            ->count();

        return view('admin.rewards.claims', compact('claims', 'status', 'pendingCount', 'claimedCount'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'accommodation' => ['nullable', 'string', 'max:1000'],
            'requirements' => ['nullable', 'string', 'max:1000'],
            'requirement_type' => ['required', Rule::in([
                Reward::REQUIREMENT_SALES_MTD,
                Reward::REQUIREMENT_SOLD_MINED_LEADS,
                Reward::REQUIREMENT_SOLD_VERIFIED_LEADS,
                Reward::REQUIREMENT_COMPLETED_PRODUCTION_TASKS,
            ])],
            'requirement_amount' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'reward_scope' => ['required', Rule::in([Reward::SCOPE_INDIVIDUAL, Reward::SCOPE_TEAM, Reward::SCOPE_COMPANY])],
            'audience' => ['required', Rule::in([
                Reward::AUDIENCE_ALL,
                Reward::AUDIENCE_COMMISSION_ELIGIBLE,
                Reward::AUDIENCE_LEAD_GENERATION,
                Reward::AUDIENCE_PRODUCTION,
            ])],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'media' => ['nullable', 'array', 'max:8'],
            'media.*' => ['file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm', 'max:51200'],
        ]);

        $reward = Reward::create([
            ...collect($validated)->except('media')->all(),
            'created_by' => $request->user()->id,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->storeMedia($reward, $request);

        return redirect()
            ->route('admin.rewards.index')
            ->with('success', 'Reward created successfully.');
    }

    public function destroy(Request $request, Reward $reward): RedirectResponse
    {
        $this->ensureAdmin($request);

        $reward->media->each(fn (RewardMedia $media) => Storage::disk('public')->delete($media->path));
        $reward->delete();

        return back()->with('success', 'Reward removed successfully.');
    }

    public function markClaimed(Request $request, RewardUnlock $rewardUnlock): RedirectResponse
    {
        $this->ensureAdmin($request);

        abort_unless($rewardUnlock->claim_requested_at, 404);

        $rewardUnlock->forceFill([
            'claimed_at' => $rewardUnlock->claimed_at ?? now(),
        ])->save();

        return redirect()
            ->route('admin.rewards.claims', ['status' => 'claimed'])
            ->with('success', 'Reward claim marked as claimed.');
    }

    private function storeMedia(Reward $reward, Request $request): void
    {
        collect($request->file('media', []))
            ->values()
            ->each(function ($file, int $index) use ($reward) {
                $mime = (string) $file->getMimeType();
                $type = str_starts_with($mime, 'video/') ? 'video' : 'image';

                $reward->media()->create([
                    'type' => $type,
                    'path' => $file->store('rewards', 'public'),
                    'original_name' => $file->getClientOriginalName(),
                    'sort_order' => $index,
                ]);
            });
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
                || $request->user()?->hasPermission('manage_rewards'),
            403
        );
    }
}
