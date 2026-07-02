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
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Top 5 Sales Performance of the Month</h3>
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
