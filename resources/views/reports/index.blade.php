<x-app-layout>
    <x-slot name="header">
        Reports
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Reports</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Summary reports from successful sales, finance records, lead credits, and production projects.
            </p>
        </div>

        <form method="GET"
              x-data="{
                  searchOpen: false,
                  searchQuery: @js($filters['search']),
                  searchOptions: @js($reportSearchOptions),
                  filteredSearchOptions() {
                      const query = (this.searchQuery || '').toLowerCase().trim();

                      if (! query) {
                          return this.searchOptions.slice(0, 8);
                      }

                      return this.searchOptions
                          .filter((option) => [option.value, option.label, option.helper]
                              .join(' ')
                              .toLowerCase()
                              .includes(query))
                          .slice(0, 8);
                  },
                  selectSearchOption(option) {
                      this.searchQuery = option.value;
                      this.searchOpen = false;
                  },
              }"
              class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="grid gap-4 lg:grid-cols-[1.2fr_1fr_1fr_2fr_auto_auto] lg:items-end">
                <div>
                    <label for="report_type" class="text-sm font-semibold text-slate-700 dark:text-zinc-300">Report Type</label>
                    <select id="report_type"
                            name="report_type"
                            class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary,#d97706)] focus:ring-[var(--brand-primary,#d97706)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <option value="">Select report</option>
                        <option value="production" @selected($filters['report_type'] === 'production')>Production Reports</option>
                        <option value="sales" @selected($filters['report_type'] === 'sales')>Sales Reports</option>
                        <option value="finance" @selected($filters['report_type'] === 'finance')>Finance Reports</option>
                        <option value="lead_miner" @selected($filters['report_type'] === 'lead_miner')>Lead Miner Reports</option>
                    </select>
                    <x-input-error :messages="$errors->get('report_type')" class="mt-2" />
                </div>

                <div>
                    <label for="start_date" class="text-sm font-semibold text-slate-700 dark:text-zinc-300">From Date</label>
                    <x-date-picker id="start_date"
                                   name="start_date"
                                   :value="$filters['start_date']"
                                   placeholder="Select from date"
                                   class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                    <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                </div>

                <div>
                    <label for="end_date" class="text-sm font-semibold text-slate-700 dark:text-zinc-300">To Date</label>
                    <x-date-picker id="end_date"
                                   name="end_date"
                                   :value="$filters['end_date']"
                                   placeholder="Select to date"
                                   class="mt-2 h-11 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                    <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                </div>

                <div class="relative" x-on:click.outside="searchOpen = false">
                    <label for="search" class="text-sm font-semibold text-slate-700 dark:text-zinc-300">Search</label>
                    <div class="relative mt-2">
                        <input id="search"
                               name="search"
                               type="text"
                               x-model="searchQuery"
                               x-on:focus="searchOpen = true"
                               x-on:input.debounce.100ms="searchOpen = true"
                               x-on:keydown.escape.prevent="searchOpen = false"
                               placeholder="Search SE ID, author, book, agent, brand, service..."
                               autocomplete="off"
                               class="h-11 w-full rounded-xl border border-slate-300 bg-white px-4 pr-11 text-sm shadow-sm focus:border-[var(--brand-primary,#d97706)] focus:ring-[var(--brand-primary,#d97706)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="button"
                                x-on:click="searchOpen = !searchOpen"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-[var(--brand-primary,#d97706)] dark:text-zinc-400"
                                aria-label="Show search suggestions">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': searchOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="searchOpen"
                         x-cloak
                         x-transition
                         class="absolute z-40 mt-2 max-h-80 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <template x-for="option in filteredSearchOptions()" :key="option.value + option.label">
                            <button type="button"
                                    x-on:click="selectSearchOption(option)"
                                    class="block w-full rounded-lg px-3 py-2 text-left hover:bg-[var(--brand-primary,#d97706)]/10">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="option.label"></span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-zinc-400" x-text="option.helper"></span>
                            </button>
                        </template>

                        <div x-show="filteredSearchOptions().length === 0" class="px-3 py-4 text-sm text-slate-500 dark:text-zinc-400">
                            No matching suggestion found. You can still search this text.
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('search')" class="mt-2" />
                </div>

                <button type="submit"
                        class="h-11 rounded-xl bg-zinc-950 px-6 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    Search
                </button>

                @if ($filters['report_type'] || $filters['search'] || $filters['start_date'] || $filters['end_date'])
                    <a href="{{ route('reports.index') }}"
                       class="inline-flex h-11 items-center justify-center rounded-xl px-4 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Clear
                    </a>
                @endif
            </div>
        </form>

        @if ($filters['report_type'])
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($summaryCards as $card)
                    @php
                        $hintClass = match ($card['tone']) {
                            'rose' => 'text-rose-600 dark:text-rose-300',
                            'sky' => 'text-sky-600 dark:text-sky-300',
                            'amber' => 'text-amber-600 dark:text-amber-300',
                            default => 'text-emerald-600 dark:text-emerald-300',
                        };
                    @endphp
                    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                        <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                        <p class="mt-5 text-3xl font-bold text-slate-950 dark:text-zinc-100">{{ $card['count'] }}</p>
                        <p class="mt-2 text-sm {{ $hintClass }}">{{ $card['hint'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="flex flex-col gap-4 border-b border-slate-200 px-6 py-4 dark:border-zinc-800 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Report Directory</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                        Select a report type above to load records.
                    </p>
                </div>

                @if ($filters['report_type'])
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('reports.export', ['format' => 'csv'] + request()->only(['report_type', 'search', 'start_date', 'end_date'])) }}"
                           class="inline-flex h-10 items-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 text-sm font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                            Export CSV
                        </a>
                        <a href="{{ route('reports.export', ['format' => 'pdf'] + request()->only(['report_type', 'search', 'start_date', 'end_date'])) }}"
                           class="inline-flex h-10 items-center rounded-xl border border-amber-200 bg-amber-50 px-4 text-sm font-semibold text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                            Export PDF
                        </a>
                    </div>
                @endif
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-4">Type</th>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Reference</th>
                            <th class="px-6 py-4">Brand / Account</th>
                            <th class="px-6 py-4">Author / Book</th>
                            <th class="px-6 py-4">Agent</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($reportRows as $row)
                            <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">
                                        {{ $row['type'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $row['date']?->format('M d, Y') ?: '-' }}
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                    {{ $row['reference'] }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $row['brand'] }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="block font-semibold text-slate-900 dark:text-zinc-100">{{ $row['author'] }}</span>
                                    <span class="mt-1 block max-w-xs truncate text-slate-500 dark:text-zinc-400">{{ $row['book_title'] }}</span>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $row['agent'] }}
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                                        {{ $row['status'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-900 dark:text-zinc-100">
                                    {{ is_null($row['amount']) ? '-' : '$' . number_format((float) $row['amount'], 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-14 text-center text-slate-500 dark:text-zinc-400">
                                    {{ $filters['report_type'] ? 'No report data matched the selected filter.' : 'Select a report type to view report data.' }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
