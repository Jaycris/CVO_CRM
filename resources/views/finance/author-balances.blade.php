<x-app-layout>
    <x-slot name="header">
        Author Balances
    </x-slot>

    @php
        $money = fn ($value) => '$' . number_format((float) $value, 2);
        $tabLink = function (string $target, string $label, int $count) use ($tab) {
            $url = request()->fullUrlWithQuery(['tab' => $target, 'page' => null]);
            $active = $tab === $target;

            return [
                'url' => $url,
                'label' => $label,
                'count' => $count,
                'class' => $active
                    ? 'rounded-lg bg-[var(--brand-primary)] px-4 py-2 text-sm font-bold text-white shadow-sm'
                    : 'rounded-lg px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800',
            ];
        };
        $tabs = [
            $tabLink('pending', 'Pending Balance', $summary['pending_count']),
            $tabLink('paid', 'Fully Paid', $summary['paid_count']),
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Author Balances</h1>
                <p class="mt-1 text-slate-500 dark:text-zinc-400">
                    Track each author's contract balance by SE ID.
                </p>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Contract Total</p>
                <p class="mt-3 text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $money($summary['contract_total']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Paid Total</p>
                <p class="mt-3 text-2xl font-bold text-[var(--brand-primary)]">{{ $money($summary['paid_total']) }}</p>
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Remaining Balance</p>
                <p class="mt-3 text-2xl font-bold text-rose-600 dark:text-rose-300">{{ $money($summary['balance_total']) }}</p>
            </div>
        </div>

        <form method="GET" action="{{ route('finance.author-balances.index') }}" class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="grid gap-4 lg:grid-cols-3">
                @if ($canViewAll && $brands->isNotEmpty())
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

                <div>
                    <label for="search" class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Search</label>
                    <input
                        id="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="SE ID, author, book, service, agent..."
                        class="mt-2 w-full rounded-lg border-slate-300 bg-white text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                    >
                </div>

                <div class="flex items-end">
                    <button class="w-full rounded-lg bg-[var(--brand-primary)] px-5 py-2.5 text-sm font-bold text-white shadow-sm hover:opacity-90">
                        Search
                    </button>
                </div>
            </div>
        </form>

        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
            <div class="flex flex-wrap gap-2 border-b border-slate-200 p-4 dark:border-zinc-800">
                @foreach ($tabs as $item)
                    <a href="{{ $item['url'] }}" class="{{ $item['class'] }}">
                        {{ $item['label'] }}
                        <span class="ml-2 rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ $item['count'] }}</span>
                    </a>
                @endforeach
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1160px] divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-900 dark:text-zinc-400">
                        <tr>
                            <th class="px-4 py-3 text-left font-bold">SE ID</th>
                            <th class="px-4 py-3 text-left font-bold">Author / Book</th>
                            @if ($canViewAll)
                                <th class="px-4 py-3 text-left font-bold">Brand</th>
                                <th class="px-4 py-3 text-left font-bold">Agent</th>
                            @endif
                            <th class="px-4 py-3 text-left font-bold">Service</th>
                            <th class="px-4 py-3 text-left font-bold">Contract</th>
                            <th class="px-4 py-3 text-left font-bold">Paid</th>
                            <th class="px-4 py-3 text-left font-bold">Balance</th>
                            <th class="px-4 py-3 text-left font-bold">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($rows as $row)
                            @php
                                $endorsement = $row['endorsement'];
                                $agentName = trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? ''));
                            @endphp
                            <tr class="align-top text-slate-700 dark:text-zinc-200">
                                <td class="whitespace-nowrap px-4 py-4 font-semibold text-amber-700 dark:text-amber-200">
                                    {{ $endorsement->endorsement_code ?: '-' }}
                                </td>
                                <td class="min-w-64 px-4 py-4">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $endorsement->author_name ?: '-' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ $endorsement->book_title ?: '-' }}</p>
                                </td>
                                @if ($canViewAll)
                                    <td class="px-4 py-4">{{ $endorsement->brand?->imprint_name ?: '-' }}</td>
                                    <td class="px-4 py-4">{{ $agentName ?: '-' }}</td>
                                @endif
                                <td class="max-w-56 px-4 py-4">{{ $endorsement->services ?: '-' }}</td>
                                <td class="whitespace-nowrap px-4 py-4 font-semibold">{{ $money($row['contract_amount']) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 font-semibold text-[var(--brand-primary)]">{{ $money($row['paid_amount']) }}</td>
                                <td class="whitespace-nowrap px-4 py-4 font-semibold {{ $row['remaining_balance'] > 0 ? 'text-rose-600 dark:text-rose-300' : 'text-emerald-600 dark:text-emerald-300' }}">
                                    {{ $money($row['remaining_balance']) }}
                                </td>
                                <td class="min-w-40 px-4 py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400">{{ number_format($row['paid_percent'], 2) }}%</span>
                                    </div>
                                    <div class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-zinc-800">
                                        <div class="h-2 rounded-full bg-[var(--brand-primary)]" style="width: {{ $row['paid_percent'] }}%"></div>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canViewAll ? 9 : 7 }}" class="px-5 py-16 text-center text-slate-500 dark:text-zinc-400">
                                    No author balances found.
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
