<x-app-layout>
    <x-slot name="header">
        Dashboard
    </x-slot>

    <div class="space-y-8">
        <div
            x-data="{
                now: new Date(),
                timer: null,
                userName: @js(auth()->user()->first_name ?? 'there'),
                init() {
                    this.timer = setInterval(() => this.now = new Date(), 1000);
                },
                hour() {
                    return this.now.getHours();
                },
                greeting() {
                    if (this.hour() < 12) return 'Good Morning';
                    if (this.hour() < 18) return 'Good Afternoon';
                    return 'Good Evening';
                },
                scene() {
                    if (this.hour() < 12) return 'Sunrise';
                    if (this.hour() < 18) return 'Daylight';
                    return 'Moonlight';
                },
                time() {
                    return this.now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                },
                date() {
                    return this.now.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
                },
                isMorning() {
                    return this.hour() < 12;
                },
                isAfternoon() {
                    return this.hour() >= 12 && this.hour() < 18;
                },
                isEvening() {
                    return this.hour() >= 18;
                },
            }"
            class="overflow-hidden rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
        >
            <div class="flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
                <div>
                    <p class="text-sm font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300" x-text="scene()"></p>
                    <h2 class="mt-2 text-2xl font-bold text-slate-900 dark:text-zinc-100" x-text="greeting() + ', ' + userName"></h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400" x-text="date()"></p>
                </div>

                <div class="flex items-center gap-4">
                    <div
                        class="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-full ring-1 transition-colors duration-700"
                        x-bind:class="{
                            'bg-amber-50 ring-amber-100 dark:bg-amber-400/10 dark:ring-amber-400/20': isMorning(),
                            'bg-sky-50 ring-sky-100 dark:bg-sky-400/10 dark:ring-sky-400/20': isAfternoon(),
                            'bg-indigo-950 ring-zinc-700': isEvening()
                        }"
                    >
                        <template x-if="isMorning()">
                            <div class="relative h-12 w-12 animate-[gentleSpin_12s_linear_infinite]">
                                <div class="absolute inset-3 rounded-full bg-amber-400 shadow-lg shadow-amber-300/70"></div>
                                <div class="absolute left-1/2 top-0 h-3 w-1 -translate-x-1/2 rounded-full bg-amber-400"></div>
                                <div class="absolute bottom-0 left-1/2 h-3 w-1 -translate-x-1/2 rounded-full bg-amber-400"></div>
                                <div class="absolute left-0 top-1/2 h-1 w-3 -translate-y-1/2 rounded-full bg-amber-400"></div>
                                <div class="absolute right-0 top-1/2 h-1 w-3 -translate-y-1/2 rounded-full bg-amber-400"></div>
                                <div class="absolute left-2 top-2 h-1 w-3 rotate-45 rounded-full bg-amber-400"></div>
                                <div class="absolute right-2 top-2 h-1 w-3 -rotate-45 rounded-full bg-amber-400"></div>
                                <div class="absolute bottom-2 left-2 h-1 w-3 -rotate-45 rounded-full bg-amber-400"></div>
                                <div class="absolute bottom-2 right-2 h-1 w-3 rotate-45 rounded-full bg-amber-400"></div>
                            </div>
                        </template>

                        <template x-if="isAfternoon()">
                            <div class="relative h-12 w-12">
                                <div class="absolute inset-1 animate-pulse rounded-full bg-yellow-400 shadow-lg shadow-yellow-300/70"></div>
                                <div class="absolute -bottom-1 -left-2 h-5 w-12 animate-[floatCloud_4s_ease-in-out_infinite] rounded-full bg-white shadow-sm"></div>
                                <div class="absolute bottom-2 left-1 h-6 w-7 animate-[floatCloud_4s_ease-in-out_infinite] rounded-full bg-white"></div>
                            </div>
                        </template>

                        <template x-if="isEvening()">
                            <div class="relative h-12 w-12 animate-[moonDrift_4s_ease-in-out_infinite]">
                                <div class="absolute left-2 top-2 h-10 w-10 rounded-full bg-slate-100 shadow-lg shadow-slate-200/30"></div>
                                <div class="absolute left-5 top-1 h-10 w-10 rounded-full bg-indigo-950"></div>
                                <div class="absolute -left-1 top-2 h-1.5 w-1.5 animate-pulse rounded-full bg-white"></div>
                                <div class="absolute right-0 top-7 h-1 w-1 animate-pulse rounded-full bg-white"></div>
                                <div class="absolute left-3 bottom-0 h-1 w-1 animate-pulse rounded-full bg-white"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        @if ($dashboardBanners->isNotEmpty())
            <div class="grid grid-cols-1 gap-5">
                @foreach ($dashboardBanners as $banner)
                    @php
                        $brandPrimary = $banner->brand?->primary_color ?: '#064e3b';
                        $brandAccent = $banner->brand?->accent_color ?: '#d97706';
                        $bannerImage = $banner->imageUrl();
                        $typeLabel = [
                            'congratulations' => 'Congratulations',
                            'event' => 'Upcoming Event',
                            'announcement' => 'Announcement',
                        ][$banner->type] ?? 'Announcement';
                    @endphp

                    <section
                        class="relative overflow-hidden rounded-2xl p-6 text-white shadow-sm ring-1 ring-white/10 md:p-8"
                        style="background: linear-gradient(135deg, {{ $brandPrimary }} 0%, #07170f 58%, {{ $brandAccent }} 130%);"
                    >
                        <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 85% 10%, rgba(255,255,255,.35), transparent 28%);"></div>
                        <div class="relative grid grid-cols-1 gap-6 md:grid-cols-[1fr_16rem] md:items-center">
                            <div>
                                <span class="inline-flex rounded-full bg-white/12 px-3 py-1 text-xs font-bold uppercase tracking-wide text-white ring-1 ring-white/20">
                                    {{ $typeLabel }}
                                </span>
                                <h2 class="mt-4 max-w-4xl text-2xl font-bold leading-tight md:text-4xl">{{ $banner->title }}</h2>
                                <p class="mt-3 max-w-3xl text-sm leading-6 text-white/80 md:text-base">{{ $banner->message }}</p>

                                @if ($banner->button_text && $banner->button_url)
                                    <a href="{{ $banner->button_url }}"
                                       class="mt-5 inline-flex rounded-xl bg-white px-5 py-3 text-sm font-bold shadow-sm transition hover:-translate-y-0.5"
                                       style="color: {{ $brandPrimary }};">
                                        {{ $banner->button_text }}
                                    </a>
                                @endif
                            </div>

                            @if ($bannerImage)
                                <div class="hidden justify-end md:flex">
                                    <img src="{{ $bannerImage }}"
                                         alt="{{ $banner->title }}"
                                         class="h-48 w-64 rounded-2xl object-cover shadow-2xl ring-1 ring-white/20">
                                </div>
                            @endif
                        </div>
                    </section>
                @endforeach
            </div>
        @endif

        @php
            $salesMtdGlobal = $salesMtdSummary['global'] ?? ['mtd' => 0, 'target' => 0, 'remaining' => 0, 'percent' => 0];
            $salesMtdBrandSnapshots = $salesMtdBrandSnapshots ?? collect();
            $isAdminDashboard = auth()->user()?->role?->name === 'Admin';
        @endphp

        <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            @if ($isAdminDashboard)
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-[var(--brand-primary)] dark:text-[var(--brand-accent)]">
                            Sales MTD Snapshot by Brand
                        </p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Only brands marked for sales reporting are shown here.
                        </p>
                    </div>
                    <p class="text-sm font-bold text-slate-900 dark:text-zinc-100">
                        ${{ number_format((float) $salesMtdGlobal['mtd'], 2) }}
                        <span class="font-semibold text-slate-500 dark:text-zinc-400">total MTD</span>
                    </p>
                </div>

                <div @class([
                    'mt-5 grid grid-cols-1 gap-4',
                    'lg:grid-cols-2 2xl:grid-cols-3' => $salesMtdBrandSnapshots->count() > 1,
                ])>
                    @forelse ($salesMtdBrandSnapshots as $snapshot)
                        @php
                            $brand = $snapshot['brand'];
                            $brandSummary = $snapshot['summary'] ?? ['mtd' => 0, 'target' => 0, 'remaining' => 0, 'percent' => 0];
                        @endphp

                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-bold text-slate-900 dark:text-zinc-100">{{ $brand->imprint_name }}</p>
                                    <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">Sales brand</p>
                                </div>
                                <span class="text-sm font-bold text-slate-900 dark:text-zinc-100">
                                    {{ number_format((float) $brandSummary['percent'], 2) }}%
                                </span>
                            </div>

                            <h3 class="mt-4 text-2xl font-bold text-slate-900 dark:text-zinc-100">
                                ${{ number_format((float) $brandSummary['mtd'], 2) }}
                            </h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                of ${{ number_format((float) $brandSummary['target'], 2) }} target
                            </p>

                            <div class="mt-4 h-3 overflow-hidden rounded-full bg-white dark:bg-zinc-800">
                                <div class="h-3 rounded-full bg-[var(--brand-primary)] transition-all" style="width: {{ min((float) $brandSummary['percent'], 100) }}%;"></div>
                            </div>

                            <p class="mt-3 text-sm text-slate-500 dark:text-zinc-400">
                                Remaining:
                                <span class="font-bold text-rose-600 dark:text-rose-300">${{ number_format((float) $brandSummary['remaining'], 2) }}</span>
                            </p>
                        </div>
                    @empty
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-400">
                            No brands are marked for sales reporting yet.
                        </div>
                    @endforelse
                </div>
            @else
                <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-[var(--brand-primary)] dark:text-[var(--brand-accent)]">
                            Sales MTD Snapshot
                        </p>
                        <p class="mt-1 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-zinc-500">
                            {{ $dashboardBrandName ?? 'All Brands' }}
                        </p>
                        <h3 class="mt-2 text-2xl font-bold text-slate-900 dark:text-zinc-100">
                            ${{ number_format((float) $salesMtdGlobal['mtd'], 2) }}
                            <span class="text-base font-semibold text-slate-500 dark:text-zinc-400">
                                of ${{ number_format((float) $salesMtdGlobal['target'], 2) }} target
                            </span>
                        </h3>
                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                            Remaining Target MTD:
                            <span class="font-bold text-rose-600 dark:text-rose-300">${{ number_format((float) $salesMtdGlobal['remaining'], 2) }}</span>.
                            PHP commission totals use the exchange rate saved in Commission Settings.
                        </p>
                    </div>

                    <div class="w-full lg:max-w-md">
                        <div class="flex justify-between text-sm font-semibold text-slate-600 dark:text-zinc-300">
                            <span>Global MTD Progress</span>
                            <span>{{ number_format((float) $salesMtdGlobal['percent'], 2) }}%</span>
                        </div>
                        <div class="mt-3 h-4 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                            <div class="h-4 rounded-full bg-[var(--brand-primary)] transition-all" style="width: {{ min((float) $salesMtdGlobal['percent'], 100) }}%;"></div>
                        </div>
                    </div>
                </div>
            @endif
        </section>

        @if (($dashboardRewards ?? collect())->isNotEmpty())
            <section
                x-data="{
                    timer: null,
                    init() {
                        if ({{ $dashboardRewards->count() }} > 1) {
                            this.startAutoSlide();
                        }
                    },
                    startAutoSlide() {
                        if ({{ $dashboardRewards->count() }} <= 1) {
                            return;
                        }

                        this.stopAutoSlide();
                        this.timer = setInterval(() => this.scrollByCard(1), 6000);
                    },
                    stopAutoSlide() {
                        if (this.timer) {
                            clearInterval(this.timer);
                            this.timer = null;
                        }
                    },
                    scrollByCard(direction) {
                        const track = this.$refs.rewardsTrack;
                        const distance = track.clientWidth;
                        const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 8;

                        if (direction > 0 && atEnd) {
                            track.scrollTo({ left: 0, behavior: 'smooth' });
                            return;
                        }

                        track.scrollBy({
                            left: direction * distance,
                            behavior: 'smooth'
                        });
                    },
                    destroy() {
                        this.stopAutoSlide();
                    },
                }"
                @mouseenter="stopAutoSlide()"
                @mouseleave="startAutoSlide()"
                class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Rewards</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Available perks based on monthly progress.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($dashboardRewards->count() > 1)
                            <button type="button"
                                    @click="scrollByCard(-1)"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    aria-label="Previous rewards">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                                </svg>
                            </button>
                            <button type="button"
                                    @click="scrollByCard(1)"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-900 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                    aria-label="Next rewards">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        @endif
                        <a href="{{ route('rewards.index') }}"
                           class="rounded-xl bg-[var(--brand-primary)] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:opacity-90">
                            View All
                        </a>
                    </div>
                </div>

                <div x-ref="rewardsTrack" class="mt-5 flex gap-4 overflow-x-auto pb-1 snap-x snap-mandatory [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                    @foreach ($dashboardRewards as $reward)
                        @php
                            $media = $reward->media->first();
                            $progressAmount = (float) $reward->getAttribute('progress_amount');
                            $requirementAmount = (float) $reward->requirement_amount;
                            $unlock = $reward->getAttribute('user_unlock');
                            $sameTierClaim = $reward->getAttribute('same_tier_claim');
                        @endphp

                        <article class="w-full flex-none snap-start overflow-hidden rounded-xl border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-950">
                            <div class="grid grid-cols-1 lg:grid-cols-[minmax(16rem,26rem)_minmax(0,1fr)]">
                                @if ($media)
                                    @if ($media->type === 'video')
                                        <video src="{{ asset('storage/' . $media->path) }}" class="h-56 w-full bg-slate-100 object-cover dark:bg-zinc-900 lg:h-72" muted controls></video>
                                    @else
                                        <img src="{{ asset('storage/' . $media->path) }}" alt="{{ $reward->title }}" class="h-56 w-full bg-slate-100 object-cover dark:bg-zinc-900 lg:h-72">
                                    @endif
                                @else
                                    <div class="flex h-56 items-center justify-center bg-emerald-50 text-sm font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200 lg:h-72">
                                        Reward
                                    </div>
                                @endif

                                <div class="flex min-w-0 flex-col justify-between gap-5 overflow-hidden p-5 lg:py-6 lg:pl-8 lg:pr-10">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                                {{ $reward->scopeLabel() }} Reward
                                            </span>
                                            @if ($unlock?->claimed_at)
                                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">Claimed</span>
                                            @elseif ($unlock?->claim_requested_at)
                                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">Claim Pending</span>
                                            @elseif ($sameTierClaim)
                                                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-400/10 dark:text-amber-200">Unavailable</span>
                                            @else
                                                <span @class([
                                                    'rounded-full px-2.5 py-1 text-xs font-bold',
                                                    'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200' => $reward->getAttribute('is_unlocked'),
                                                    'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $reward->getAttribute('is_unlocked'),
                                                ])>
                                                    {{ $reward->getAttribute('is_unlocked') ? 'Unlocked' : 'In Progress' }}
                                                </span>
                                            @endif
                                        </div>

                                        <h4 class="mt-4 break-words text-2xl font-bold text-slate-950 dark:text-white">{{ $reward->title }}</h4>

                                        @if ($reward->accommodation)
                                            <p class="mt-3 max-w-2xl break-words text-sm leading-6 text-slate-600 dark:text-zinc-300">
                                                {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', trim($reward->accommodation)), 260) }}
                                            </p>
                                        @endif

                                        @if ($reward->requirements)
                                            <p class="mt-3 max-w-2xl break-words text-sm leading-6 text-slate-500 dark:text-zinc-400">
                                                <span class="font-semibold text-slate-700 dark:text-zinc-300">Additional requirements:</span>
                                                {{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', trim($reward->requirements)), 140) }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="grid min-w-0 gap-4">
                                        <div class="min-w-0 rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                            <div class="flex flex-wrap items-center justify-between gap-3 text-sm font-semibold text-slate-600 dark:text-zinc-300">
                                                <span>{{ $reward->getAttribute('progress_title') }}</span>
                                                <span class="break-words text-slate-950 dark:text-white">
                                                    {{ $reward->getAttribute('progress_label') }} / {{ $reward->getAttribute('requirement_label') }}
                                                </span>
                                            </div>
                                            <div class="mt-3 h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-zinc-800">
                                                <div class="h-full rounded-full bg-emerald-600 dark:bg-emerald-400" style="width: {{ $reward->getAttribute('progress_percent') }}%"></div>
                                            </div>
                                        </div>

                                        @if ($unlock?->claimed_at)
                                            <p class="rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                                Claimed {{ $unlock->claimed_at->format('M d, Y') }}
                                            </p>
                                        @elseif ($sameTierClaim)
                                            <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 dark:bg-amber-400/10 dark:text-amber-200">
                                                Selected {{ $sameTierClaim->reward?->title ?? 'another reward' }}
                                            </p>
                                        @elseif ($reward->getAttribute('is_unlocked') && $unlock?->expires_at)
                                            <p class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700 dark:bg-amber-400/10 dark:text-amber-200">
                                                Expires {{ $unlock->expires_at->format('M d, Y') }}
                                            </p>
                                        @elseif ($reward->expires_in_days)
                                            <p class="text-sm text-slate-500 dark:text-zinc-400 lg:max-w-56">
                                                Reach the requirement to reveal the reward expiration date.
                                            </p>
                                        @endif
                                    </div>

                                    @if ($reward->getAttribute('is_unlocked'))
                                        <div>
                                            <a href="{{ route('rewards.claim.show', $sameTierClaim?->reward ?? $reward) }}"
                                               class="inline-flex min-h-10 items-center justify-center rounded-xl bg-emerald-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-800">
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
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-4">
            @php
                $cardToneClasses = [
                    'emerald' => 'text-emerald-600 dark:text-emerald-300',
                    'amber' => 'text-amber-600 dark:text-amber-300',
                    'sky' => 'text-sky-600 dark:text-sky-300',
                    'rose' => 'text-rose-600 dark:text-rose-300',
                    'slate' => 'text-slate-500 dark:text-zinc-400',
                ];
            @endphp

            @foreach ($dashboardCards as $card)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">
                        @if (($card['format'] ?? null) === 'currency')
                            ${{ number_format((float) $card['count'], 2) }}
                        @else
                            {{ number_format($card['count']) }}
                        @endif
                    </h3>
                    <p class="mt-2 text-sm {{ $cardToneClasses[$card['tone']] ?? $cardToneClasses['slate'] }}">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800 xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Sales Dashboard MTD</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Successful payment totals by agent.</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-400/15 dark:text-amber-200">
                        {{ now()->format('F Y') }}
                    </span>
                </div>

                <div class="mt-5 overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="w-16 px-5 py-3">Rank</th>
                                <th class="px-5 py-3">Agent Name</th>
                                <th class="px-5 py-3 text-right">Total Amount Sold</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($topSalesPerformance as $index => $performance)
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                    <td class="px-5 py-4 font-bold text-amber-700 dark:text-amber-300">#{{ $index + 1 }}</td>
                                    <td class="px-5 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $performance['agent_name'] }}</td>
                                    <td class="px-5 py-4 text-right font-bold text-slate-900 dark:text-zinc-100">
                                        ${{ number_format($performance['total_amount'], 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">
                                        No successful sales recorded this month yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Total Sales This Month</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Current month compared with last month.</p>
                    </div>
                </div>

                <div class="mt-6 space-y-6">
                    @foreach ($monthlySalesComparison as $month)
                        <div>
                            <div class="flex items-end justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ $month['short_label'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ $month['label'] }}</p>
                                </div>
                                <p class="text-lg font-bold {{ $month['text_color'] }}">
                                    ${{ number_format($month['total'], 2) }}
                                </p>
                            </div>
                            <div class="mt-3 h-4 rounded-full bg-slate-100 dark:bg-zinc-800">
                                <div
                                    class="h-4 rounded-full {{ $month['color'] }}"
                                    style="width: {{ $month['width'] }}%; min-width: {{ $month['total'] > 0 ? '2rem' : '0' }};"
                                ></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Notes Overview</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Your latest personal notes.</p>
                    </div>
                    <a href="{{ route('notes.index') }}"
                       class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Open
                    </a>
                </div>

                @if ($recentNotes->count())
                    <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @foreach ($recentNotes as $note)
                            <article class="px-6 py-4">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h4 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $note->title }}</h4>
                                        <p class="mt-1 line-clamp-2 text-sm leading-6 text-slate-500 dark:text-zinc-400">{{ $note->body }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-400/15 dark:text-amber-200">
                                        {{ $note->updated_at->format('M d') }}
                                    </span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">No notes yet</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Your newest notes will appear here.</p>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Calendar Overview</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Upcoming personal to-do items.</p>
                    </div>
                    <a href="{{ route('calendar.index') }}"
                       class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Open
                    </a>
                </div>

                @if ($upcomingCalendarTodos->count())
                    <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                        @foreach ($upcomingCalendarTodos as $todo)
                            <div class="flex items-start gap-4 px-6 py-4">
                                <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200">
                                    <span class="text-[10px] font-bold uppercase">{{ $todo->due_date->format('M') }}</span>
                                    <span class="text-lg font-bold leading-none">{{ $todo->due_date->format('d') }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $todo->title }}</h4>
                                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                        {{ $todo->due_date->format('l') }}
                                        @if ($todo->due_time)
                                            at {{ $todo->due_time->format('h:i A') }}
                                        @endif
                                    </p>
                                    @if ($todo->notes)
                                        <p class="mt-1 line-clamp-1 text-sm text-slate-500 dark:text-zinc-400">{{ $todo->notes }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="px-6 py-12 text-center">
                        <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">No upcoming to-dos</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Your next calendar items will appear here.</p>
                    </div>
                @endif
            </section>
        </div>

    </div>
</x-app-layout>
