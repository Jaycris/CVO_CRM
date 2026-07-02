<x-app-layout>
    <x-slot name="header">
        Calendar
    </x-slot>

    @php
        $days = \Carbon\CarbonPeriod::create($calendarStart, $calendarEnd);
        $previousMonth = $month->copy()->subMonthNoOverflow()->format('Y-m');
        $nextMonth = $month->copy()->addMonthNoOverflow()->format('Y-m');
    @endphp

    <div class="space-y-6"
         x-data="{ selectedDate: @js(old('due_date', now()->toDateString())) }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Calendar</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Add personal to-do items and track your schedule.</p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('calendar.index', ['month' => $previousMonth]) }}"
                   class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    Previous
                </a>
                <a href="{{ route('calendar.index', ['month' => now()->format('Y-m')]) }}"
                   class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    Today
                </a>
                <a href="{{ route('calendar.index', ['month' => $nextMonth]) }}"
                   class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-800">
                    Next
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1fr_380px]">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">{{ $month->format('F Y') }}</h2>
                </div>

                <div class="border-b border-slate-200 bg-slate-50 text-xs font-bold uppercase text-slate-500 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-500"
                     style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">
                    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                        <div class="border-r border-slate-100 px-3 py-3 text-center last:border-r-0 dark:border-zinc-800">{{ $weekday }}</div>
                    @endforeach
                </div>

                <div style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">
                    @foreach ($days as $day)
                        @php
                            $dateKey = $day->toDateString();
                            $dayTodos = $todos->get($dateKey, collect());
                            $isCurrentMonth = $day->month === $month->month;
                            $isToday = $day->isToday();
                        @endphp

                        <button type="button"
                                x-on:click="selectedDate = @js($dateKey)"
                                style="min-height: 8.5rem;"
                                x-bind:class="selectedDate === @js($dateKey) ? 'ring-2 ring-inset ring-amber-400 bg-amber-50 dark:bg-amber-400/10' : ''"
                             @class([
                            'w-full text-left transition hover:bg-amber-50/70 dark:hover:bg-amber-400/10 border-b border-r border-slate-100 p-3 dark:border-zinc-800',
                            'border-r-0' => $loop->iteration % 7 === 0,
                            'bg-white dark:bg-zinc-900' => $isCurrentMonth,
                            'bg-slate-50 text-slate-400 dark:bg-zinc-950 dark:text-zinc-600' => ! $isCurrentMonth,
                        ])>
                            <div @class([
                                'mb-2 inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold',
                                'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' => $isToday,
                                'text-slate-700 dark:text-zinc-200' => ! $isToday && $isCurrentMonth,
                            ])>
                                {{ $day->day }}
                            </div>

                            <div class="space-y-1">
                                @foreach ($dayTodos->take(3) as $todo)
                                    <div @class([
                                        'rounded-lg px-2 py-1 text-xs',
                                        'bg-emerald-50 text-emerald-700 line-through dark:bg-emerald-400/10 dark:text-emerald-200' => $todo->completed_at,
                                        'bg-amber-50 text-amber-800 dark:bg-amber-400/10 dark:text-amber-100' => ! $todo->completed_at,
                                    ])>
                                        @if ($todo->due_time)
                                            <span class="font-semibold">{{ $todo->due_time->format('h:i A') }}</span>
                                        @endif
                                        {{ $todo->title }}
                                    </div>
                                @endforeach

                                @if ($dayTodos->count() > 3)
                                    <p class="text-xs font-semibold text-slate-400 dark:text-zinc-500">+{{ $dayTodos->count() - 3 }} more</p>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </div>
            </section>

            <aside class="space-y-6">
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Add To-Do</h2>

                    <form method="POST" action="{{ route('calendar.todos.store') }}" class="mt-5 space-y-4">
                        @csrf

                        <div>
                            <label for="title" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Title <span class="text-rose-500">*</span>
                            </label>
                            <input id="title" name="title" value="{{ old('title') }}" required
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('title')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label for="due_date" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                    Date <span class="text-rose-500">*</span>
                                </label>
                                <x-date-picker id="due_date" name="due_date" x-model="selectedDate" required
                                               class="w-full rounded-xl border-slate-300 px-4 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                                <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                            </div>

                            <div>
                                <label for="due_time" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Time</label>
                                <input id="due_time" type="time" name="due_time" value="{{ old('due_time') }}"
                                       class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <x-input-error :messages="$errors->get('due_time')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <label for="notes" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Details</label>
                            <textarea id="notes" name="notes" rows="4"
                                      class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>

                        <button type="submit"
                                class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Add To-Do
                        </button>
                    </form>
                </section>

                <section class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Upcoming To-Do</h2>
                    </div>

                    @if ($upcomingTodos->count())
                        <div class="divide-y divide-slate-100 dark:divide-zinc-800">
                            @foreach ($upcomingTodos as $todo)
                                <div class="p-4" x-data="{ editing: false }">
                                    <div x-show="!editing">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <h3 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $todo->title }}</h3>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-zinc-500">
                                                    {{ $todo->due_date->format('M d, Y') }}
                                                    @if ($todo->due_time)
                                                        at {{ $todo->due_time->format('h:i A') }}
                                                    @endif
                                                </p>
                                                @if ($todo->notes)
                                                    <p class="mt-2 text-sm text-slate-600 dark:text-zinc-300">{{ $todo->notes }}</p>
                                                @endif
                                            </div>

                                            <div class="flex shrink-0 items-center gap-2">
                                                <form method="POST" action="{{ route('calendar.todos.toggle', $todo) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            title="Mark complete"
                                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75" />
                                                        </svg>
                                                    </button>
                                                </form>
                                                <button type="button"
                                                        x-on:click="editing = true"
                                                        title="Edit to-do"
                                                        class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                    </svg>
                                                </button>
                                                <form method="POST" action="{{ route('calendar.todos.destroy', $todo) }}" x-on:submit="if (!confirm('Delete this to-do?')) $event.preventDefault();">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            title="Delete to-do"
                                                            class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9" />
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 6h14" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>

                                    <form x-show="editing" x-cloak method="POST" action="{{ route('calendar.todos.update', $todo) }}" class="space-y-3">
                                        @csrf
                                        @method('PUT')
                                        <input name="title" value="{{ $todo->title }}" required
                                               class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        <div class="grid grid-cols-2 gap-3">
                                            <x-date-picker name="due_date" :value="$todo->due_date->toDateString()" required
                                                           class="w-full rounded-xl border-slate-300 px-4 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                                            <input type="time" name="due_time" value="{{ $todo->due_time?->format('H:i') }}"
                                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        </div>
                                        <textarea name="notes" rows="3"
                                                  class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ $todo->notes }}</textarea>
                                        <div class="flex justify-end gap-2">
                                            <button type="button"
                                                    x-on:click="editing = false"
                                                    class="rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50 dark:border-zinc-700 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                                Cancel
                                            </button>
                                            <button type="submit"
                                                    class="rounded-xl bg-zinc-950 px-3 py-2 text-sm font-semibold text-amber-100 hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                                Save
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">
                            No upcoming to-do items.
                        </div>
                    @endif
                </section>
            </aside>
        </div>
    </div>
</x-app-layout>
