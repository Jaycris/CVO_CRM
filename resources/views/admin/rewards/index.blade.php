<x-app-layout>
    <x-slot name="header">
        Rewards
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Rewards</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Create sales rewards based on monthly MTD performance.
                </p>
            </div>

            <a href="{{ route('admin.rewards.claims') }}"
               class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Reward Claims
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                Please check the form and try again.
            </div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <form method="POST" action="{{ route('admin.rewards.store') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
                    <div>
                        <label for="title" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Reward Title <span class="text-rose-600">*</span>
                        </label>
                        <input id="title" name="title" type="text" value="{{ old('title') }}" required
                               placeholder="Stay at Nustar"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('title')" class="mt-2" />
                    </div>

                    <div>
                        <label for="requirement_amount" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Required Monthly MTD <span class="text-rose-600">*</span>
                        </label>
                        <input id="requirement_amount" name="requirement_amount" type="number" min="0" step="0.01" value="{{ old('requirement_amount') }}" required
                               placeholder="15000"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('requirement_amount')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label for="accommodation" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Accommodation / Perks
                    </label>
                    <textarea id="accommodation" name="accommodation" rows="3"
                              placeholder="Good for 2, including breakfast"
                              class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('accommodation') }}</textarea>
                    <x-input-error :messages="$errors->get('accommodation')" class="mt-2" />
                </div>

                <div>
                    <label for="requirements" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Additional Requirements
                    </label>
                    <textarea id="requirements" name="requirements" rows="3"
                              placeholder="Optional: confirmed payments only, no refunds, applies to this month only"
                              class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('requirements') }}</textarea>
                    <x-input-error :messages="$errors->get('requirements')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
                    <div>
                        <p class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Reward Type</p>
                        <div class="space-y-2 rounded-xl border border-slate-200 p-3 dark:border-zinc-800">
                            <label class="flex gap-3 text-sm text-slate-700 dark:text-zinc-300">
                                <input type="radio" name="reward_scope" value="individual" @checked(old('reward_scope', 'individual') === 'individual') class="mt-1 text-emerald-700 focus:ring-emerald-600">
                                <span>Individual Rewards</span>
                            </label>
                            <label class="flex gap-3 text-sm text-slate-700 dark:text-zinc-300">
                                <input type="radio" name="reward_scope" value="team" @checked(old('reward_scope') === 'team') class="mt-1 text-emerald-700 focus:ring-emerald-600">
                                <span>This is for whole Team</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('reward_scope')" class="mt-2" />
                    </div>

                    <div>
                        <p class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Audience</p>
                        <div class="space-y-2 rounded-xl border border-slate-200 p-3 dark:border-zinc-800">
                            <label class="flex gap-3 text-sm text-slate-700 dark:text-zinc-300">
                                <input type="radio" name="audience" value="all_users" @checked(old('audience') === 'all_users') class="mt-1 text-emerald-700 focus:ring-emerald-600">
                                <span>Show this to all User</span>
                            </label>
                            <label class="flex gap-3 text-sm text-slate-700 dark:text-zinc-300">
                                <input type="radio" name="audience" value="commission_eligible" @checked(old('audience', 'commission_eligible') === 'commission_eligible') class="mt-1 text-emerald-700 focus:ring-emerald-600">
                                <span>Only Eligible Commission</span>
                            </label>
                        </div>
                        <x-input-error :messages="$errors->get('audience')" class="mt-2" />
                    </div>

                    <div>
                        <label for="expires_in_days" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Days to Claim After Unlock
                        </label>
                        <input id="expires_in_days" name="expires_in_days" type="number" min="1" max="365" value="{{ old('expires_in_days') }}"
                               placeholder="Example: 7"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                            The countdown starts only after a user unlocks this reward.
                        </p>
                        <x-input-error :messages="$errors->get('expires_in_days')" class="mt-2" />

                        <label class="mt-4 flex gap-3 text-sm text-slate-700 dark:text-zinc-300">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="mt-1 rounded border-slate-300 text-emerald-700 focus:ring-emerald-600">
                            <span>Post this reward</span>
                        </label>
                    </div>
                </div>

                <div>
                    <label for="media" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Preview Photos / Video
                    </label>
                    <input id="media" name="media[]" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
                           class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-black dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        Upload up to 8 files. Images and short marketing videos are supported.
                    </p>
                    <x-input-error :messages="$errors->get('media')" class="mt-2" />
                    <x-input-error :messages="$errors->get('media.*')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:bg-emerald-400 dark:text-zinc-950">
                        Create Reward
                    </button>
                </div>
            </form>
        </div>

        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Reward Directory</h2>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($rewards as $reward)
                    <div class="grid gap-4 px-6 py-5 lg:grid-cols-[10rem_1fr_auto]">
                        <div class="overflow-hidden rounded-xl bg-slate-100 dark:bg-zinc-800">
                            @php($media = $reward->media->first())
                            @if ($media?->type === 'video')
                                <video src="{{ asset('storage/' . $media->path) }}" class="h-32 w-full object-cover" muted controls></video>
                            @elseif ($media)
                                <img src="{{ asset('storage/' . $media->path) }}" alt="{{ $reward->title }}" class="h-32 w-full object-cover">
                            @else
                                <div class="flex h-32 items-center justify-center text-sm text-slate-400">No media</div>
                            @endif
                        </div>

                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-bold text-slate-950 dark:text-white">{{ $reward->title }}</h3>
                                <span class="rounded-full bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                    {{ $reward->reward_scope === 'team' ? 'Team' : 'Individual' }}
                                </span>
                                <span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $reward->audience === 'all_users' ? 'All Users' : 'Eligible Commission' }}
                                </span>
                                @unless ($reward->is_active)
                                    <span class="rounded-full bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-400/10 dark:text-rose-200">Hidden</span>
                                @endunless
                            </div>

                            <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ $reward->accommodation ?: 'No accommodation details.' }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                Requirement: ${{ number_format((float) $reward->requirement_amount, 2) }} MTD
                            </p>
                            @if ($reward->expires_in_days)
                                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                    Claim window: {{ $reward->expires_in_days }} {{ Str::plural('day', $reward->expires_in_days) }} after unlock
                                </p>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('admin.rewards.destroy', $reward) }}" onsubmit="return confirm('Remove this reward?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50 dark:border-rose-400/30 dark:text-rose-200 dark:hover:bg-rose-400/10">
                                Delete
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">
                        No rewards yet.
                    </div>
                @endforelse
            </div>

            @if ($rewards->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $rewards->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
