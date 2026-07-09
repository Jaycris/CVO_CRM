<x-app-layout>
    <x-slot name="header">
        Sales Activity
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Sales Activity</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Automatic records created from successful payments for future reporting.
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Sales Activity Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Use this as the clean source for sales, brand, lead miner, verifier, and service reports.
                        </p>
                    </div>

                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="search"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search activity..."
                               class="h-11 w-72 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="submit"
                                class="h-11 rounded-xl bg-zinc-950 px-5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Search
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-4">Sold Date</th>
                            <th class="px-6 py-4">SE ID</th>
                            <th class="px-6 py-4">Brand</th>
                            <th class="px-6 py-4">Agent</th>
                            <th class="px-6 py-4">Frankie / Split</th>
                            <th class="px-6 py-4">Author / Book Title</th>
                            <th class="px-6 py-4">Service</th>
                            <th class="px-6 py-4">Lead Miner</th>
                            <th class="px-6 py-4">Verifier</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($activities as $activity)
                            @php
                                $agentName = trim(($activity->agent?->first_name ?? '') . ' ' . ($activity->agent?->last_name ?? '')) ?: '-';
                                $frankieName = trim(($activity->frankieAgent?->first_name ?? '') . ' ' . ($activity->frankieAgent?->last_name ?? ''));
                                $minerName = trim(($activity->leadMiner?->first_name ?? '') . ' ' . ($activity->leadMiner?->last_name ?? '')) ?: '-';
                                $verifierName = trim(($activity->verifier?->first_name ?? '') . ' ' . ($activity->verifier?->last_name ?? '')) ?: '-';
                                $statusClasses = match ($activity->payment_status) {
                                    'Payment Success' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200',
                                    'Refund' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200',
                                    'Dispute' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300',
                                };
                            @endphp
                            <tr class="align-top hover:bg-slate-50/70 dark:hover:bg-zinc-800/60">
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $activity->sold_date?->format('M d, Y') ?? '-' }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $activity->endorsement_code ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $activity->brand?->imprint_name ?? '-' }}</td>
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-zinc-100">
                                    <p>{{ $agentName }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                                        Credit: ${{ number_format((float) ($activity->agent_credit_amount ?: $activity->amount), 2) }}
                                    </p>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">
                                    @if ($activity->frankieAgent && (float) $activity->frankie_credit_amount > 0)
                                        <p class="font-medium text-slate-900 dark:text-zinc-100">{{ $frankieName }}</p>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                                            {{ number_format((float) $activity->frankie_commission_percent, 0) }}% |
                                            ${{ number_format((float) $activity->frankie_credit_amount, 2) }}
                                        </p>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="max-w-sm px-6 py-4">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $activity->author_name ?? '-' }}</p>
                                    <p class="mt-1 text-slate-500 dark:text-zinc-400">{{ $activity->book_title ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $activity->service_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $minerName }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $verifierName }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                    ${{ number_format((float) $activity->amount, 2) }}
                                    <p class="mt-1 text-xs font-normal text-slate-500 dark:text-zinc-400">Gross sale</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                        {{ $activity->payment_status ?? 'Recorded' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-16 text-center text-slate-500 dark:text-zinc-400">
                                    No sales activity yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($activities->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $activities->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
