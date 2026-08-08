<x-app-layout>
    <x-slot name="header">
        Agent Statements
    </x-slot>

    @php
        $money = fn ($value) => '$' . number_format((float) $value, 2);
        $showBrandColumn = $canViewAll || auth()->user()?->department !== 'Sales';
        $statementColumnCount = $showBrandColumn ? 11 : 10;
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Agent Statements</h1>
                <p class="mt-1 text-slate-500 dark:text-zinc-400">
                    Monthly sales credit, service commission, markup commission, and USD totals.
                </p>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.agent-statements.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="grid gap-4 lg:grid-cols-4">
                <div>
                    <label for="month" class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Month</label>
                    <input
                        id="month"
                        name="month"
                        value="{{ $month->format('Y-m') }}"
                        placeholder="YYYY-MM"
                        class="mt-2 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                </div>

                @if ($brands->isNotEmpty())
                    <div>
                        <label for="brand_id" class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Brand</label>
                        <select
                            id="brand_id"
                            name="brand_id"
                            class="mt-2 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            <option value="">All brands</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) $brandId === $brand->id)>{{ $brand->imprint_name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($canViewAll)
                    <div>
                        <label for="agent_id" class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Agent</label>
                        <select
                            id="agent_id"
                            name="agent_id"
                            class="mt-2 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                            <option value="">All agents</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((int) $agentId === $agent->id)>
                                    {{ $agent->first_name }} {{ $agent->last_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div>
                    <label for="search" class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Search</label>
                    <div class="mt-2 flex gap-3">
                        <input
                            id="search"
                            name="search"
                            value="{{ $search }}"
                            placeholder="Author, book, service, agent..."
                            class="w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                        >
                        <button class="rounded-lg bg-[var(--brand-primary)] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:opacity-90">
                            Search
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">MTD</p>
                <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $money($totals['sales_credit']) }}</p>
                <p class="mt-2 text-sm text-[var(--brand-primary)]">Month-to-date amount</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Service MTD</p>
                <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $money($totals['service_mtd']) }}</p>
                <p class="mt-2 text-sm text-[var(--brand-primary)]">Base service credit</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Markup MTD</p>
                <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $money($totals['markup_mtd']) }}</p>
                <p class="mt-2 text-sm text-[var(--brand-primary)]">Markup credit</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">USD Commission</p>
                <p class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $money($totals['usd_total']) }}</p>
                <p class="mt-2 text-sm text-[var(--brand-primary)]">Service + markup commission</p>
            </div>
        </div>

        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Target Progress</h2>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                        {{ $money($totals['sales_credit']) }} of {{ $money($totals['target']) }} target.
                    </p>
                </div>
                <div class="text-left md:text-right">
                    <p class="text-2xl font-bold text-[var(--brand-primary)]">{{ number_format($totals['percent'], 2) }}%</p>
                    <p class="text-sm text-slate-500 dark:text-zinc-400">Remaining: {{ $money($totals['remaining']) }}</p>
                </div>
            </div>
            <div class="mt-4 h-3 rounded-full bg-slate-100 dark:bg-zinc-800">
                <div class="h-3 rounded-full bg-[var(--brand-primary)]" style="width: {{ min($totals['percent'], 100) }}%"></div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="border-b border-slate-200 p-5 dark:border-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Statement Directory</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Each row is credited from a successful payment.
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-xs dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold">Sold Date</th>
                            @if ($showBrandColumn)
                                <th class="px-4 py-3 text-left font-bold">Brand</th>
                            @endif
                            <th class="px-4 py-3 text-left font-bold">Agent</th>
                            <th class="px-4 py-3 text-left font-bold">Author / Book</th>
                            <th class="px-4 py-3 text-left font-bold">Service</th>
                            <th class="px-4 py-3 text-left font-bold">Sale Amount</th>
                            <th class="px-4 py-3 text-left font-bold">Service MTD</th>
                            <th class="px-4 py-3 text-left font-bold">Markup MTD</th>
                            <th class="px-4 py-3 text-left font-bold">Service Comm</th>
                            <th class="px-4 py-3 text-left font-bold">Markup Comm</th>
                            <th class="px-4 py-3 text-left font-bold">USD Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($rows as $row)
                            @php
                                $activity = $row['activity'];
                                $agent = $row['agent'];
                            @endphp
                            <tr class="align-top text-slate-700 dark:text-zinc-200">
                                <td class="whitespace-nowrap px-4 py-3 text-xs leading-5">{{ $activity->sold_date?->format('M d, Y') ?? '-' }}</td>
                                @if ($showBrandColumn)
                                    <td class="px-4 py-3 text-xs leading-5">{{ $activity->brand?->imprint_name ?? '-' }}</td>
                                @endif
                                <td class="px-4 py-3 text-xs font-semibold leading-5 text-slate-900 dark:text-zinc-100">
                                    {{ trim(($agent?->first_name ?? '') . ' ' . ($agent?->last_name ?? '')) ?: '-' }}
                                </td>
                                <td class="min-w-56 px-4 py-3 text-xs leading-5">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $activity->author_name ?: '-' }}</p>
                                    <p class="mt-1 text-slate-500 dark:text-zinc-400">{{ $activity->book_title ?: '-' }}</p>
                                </td>
                                <td class="max-w-40 px-4 py-3 text-xs leading-5">{{ $activity->service_name ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs font-semibold">{{ $money($row['sale_amount']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $money($row['service_amount']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">{{ $money($row['markup_amount']) }}</td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    {{ $money($row['service_commission']) }}
                                    <span class="block text-xs text-slate-400">{{ number_format($row['service_commission_percent'], 2) }}%</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-xs">
                                    {{ $money($row['markup_commission']) }}
                                    <span class="block text-xs text-slate-400">{{ number_format($row['markup_commission_percent'], 2) }}%</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-sm font-bold text-slate-900 dark:text-zinc-100">{{ $money($row['usd_total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $statementColumnCount }}" class="px-5 py-16 text-center text-slate-500 dark:text-zinc-400">
                                    No successful sales found for this statement.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($rows->hasPages())
                <div class="border-t border-slate-200 px-5 py-4 dark:border-zinc-800">
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
