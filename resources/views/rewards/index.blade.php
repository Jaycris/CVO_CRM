<x-app-layout>
    <x-slot name="header">
        Rewards
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Rewards</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    View available perks and track your monthly progress.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-3 sm:min-w-80">
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Your MTD</p>
                    <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">${{ number_format($individualMtd, 2) }}</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900">
                    <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Global MTD</p>
                    <p class="mt-1 text-xl font-bold text-slate-950 dark:text-white">${{ number_format($teamMtd, 2) }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-2">
            @forelse ($rewards as $reward)
                @php($unlock = $reward->getAttribute('user_unlock'))
                @php($sameTierClaim = $reward->getAttribute('same_tier_claim'))
                <article class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    @php($mediaItems = $reward->media)
                    @if ($mediaItems->isNotEmpty())
                        <div class="grid {{ $mediaItems->count() > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-1 bg-slate-100 dark:bg-zinc-950">
                            @foreach ($mediaItems->take(4) as $media)
                                @if ($media->type === 'video')
                                    <video src="{{ asset('storage/' . $media->path) }}" class="h-56 w-full object-cover" controls muted></video>
                                @else
                                    <img src="{{ asset('storage/' . $media->path) }}" alt="{{ $reward->title }}" class="h-56 w-full object-cover">
                                @endif
                            @endforeach
                        </div>
                    @endif

                    <div class="p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                {{ $reward->scopeLabel() }} Reward
                            </span>
                            @if ($unlock?->claimed_at)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">Claimed</span>
                            @elseif ($unlock?->claim_requested_at)
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">Claim Pending</span>
                            @elseif ($sameTierClaim)
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-400/10 dark:text-amber-200">Unavailable</span>
                            @elseif ($reward->getAttribute('is_unlocked'))
                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-400/10 dark:text-amber-200">Unlocked</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">In Progress</span>
                            @endif
                        </div>

                        <h2 class="mt-4 text-xl font-bold text-slate-950 dark:text-white">{{ $reward->title }}</h2>

                        @if ($reward->accommodation)
                            <p class="mt-3 whitespace-pre-wrap break-words text-justify text-sm leading-6 text-slate-600 dark:text-zinc-300">{{ $reward->accommodation }}</p>
                        @endif

                        <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold text-slate-700 dark:text-zinc-300">
                                    {{ $reward->getAttribute('progress_title') }}
                                </span>
                                <span class="font-bold text-slate-950 dark:text-white">
                                    {{ $reward->getAttribute('progress_label') }} / {{ $reward->getAttribute('requirement_label') }}
                                </span>
                            </div>
                            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-zinc-800">
                                <div class="h-full rounded-full bg-emerald-600 dark:bg-emerald-400" style="width: {{ $reward->getAttribute('progress_percent') }}%"></div>
                            </div>
                            @if ($reward->requirements)
                                <p class="mt-3 whitespace-pre-wrap break-words text-justify text-sm leading-6 text-slate-500 dark:text-zinc-400">
                                    <span class="font-semibold text-slate-700 dark:text-zinc-300">Additional requirements:</span>
                                    {{ $reward->requirements }}
                                </p>
                            @endif
                        </div>

                        @if ($unlock?->claimed_at)
                            <div class="mt-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                                Claimed on {{ $unlock->claimed_at->format('M d, Y') }}.
                            </div>
                        @elseif ($sameTierClaim)
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                                You already selected {{ $sameTierClaim->reward?->title ?? 'another reward' }} for this same requirement this month.
                            </div>
                        @elseif ($reward->getAttribute('is_unlocked') && $unlock?->expires_at)
                            <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                                Reward expires on {{ $unlock->expires_at->format('M d, Y') }}.
                            </div>
                        @elseif (! $reward->getAttribute('is_unlocked') && $reward->expires_in_days)
                            <p class="mt-4 text-sm text-slate-500 dark:text-zinc-400">
                                Reach the requirement to reveal the reward expiration date.
                            </p>
                        @endif

                        @if ($reward->getAttribute('is_unlocked'))
                            <div class="mt-5">
                                <a href="{{ route('rewards.claim.show', $sameTierClaim?->reward ?? $reward) }}"
                                   class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
                                    @if ($unlock?->claimed_at)
                                        View Claimed Reward
                                    @elseif ($unlock?->claim_requested_at)
                                        View Claim Request
                                    @elseif ($sameTierClaim)
                                        View Selected Reward
                                    @else
                                        Claim Reward
                                    @endif
                                </a>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="rounded-2xl bg-white px-6 py-12 text-center shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800 xl:col-span-2">
                    <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">No rewards available yet.</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">Check back soon for new perks and monthly challenges.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
