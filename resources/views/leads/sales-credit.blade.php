<x-app-layout>
    <x-slot name="header">
        {{ $pageTitle }}
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $pageDescription }}</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($summaryCards as $card)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <p class="mt-5 text-3xl font-bold text-slate-950 dark:text-zinc-100">{{ $card['count'] }}</p>
                    <p class="mt-2 text-sm text-emerald-600 dark:text-emerald-300">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $pageTitle }} Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Credit is based on successful Finance payment records.
                        </p>
                    </div>

                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="search"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search sales..."
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
                            <th class="px-6 py-4">SE ID</th>
                            <th class="px-6 py-4">Brand</th>
                            <th class="px-6 py-4">Author</th>
                            <th class="px-6 py-4">Book Title</th>
                            <th class="px-6 py-4">Service</th>
                            <th class="px-6 py-4">Agent</th>
                            <th class="px-6 py-4">Lead Miner</th>
                            <th class="px-6 py-4">Verifier</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Sold Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($payments as $payment)
                            @php
                                $endorsement = $payment->endorsement;
                                $lead = $endorsement?->lead;
                                $agentName = trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: '-';
                                $minerName = trim(($lead?->createdBy?->first_name ?? '') . ' ' . ($lead?->createdBy?->last_name ?? '')) ?: '-';
                                $verifierName = trim(($lead?->verifiedBy?->first_name ?? '') . ' ' . ($lead?->verifiedBy?->last_name ?? '')) ?: '-';
                            @endphp
                            <tr class="align-top hover:bg-slate-50/70 dark:hover:bg-zinc-800/60">
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $endorsement?->endorsement_code ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $endorsement?->brand?->imprint_name ?? $payment->brand?->imprint_name ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-900 dark:text-zinc-100">{{ $endorsement?->author_name ?? '-' }}</td>
                                <td class="max-w-xs px-6 py-4 font-medium text-slate-900 dark:text-zinc-100">{{ $endorsement?->book_title ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $endorsement?->services ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-900 dark:text-zinc-100">{{ $agentName }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $minerName }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $verifierName }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">${{ number_format((float) ($endorsement?->amount ?? 0), 2) }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $payment->sold_date?->format('M d, Y') ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center text-slate-500 dark:text-zinc-400">
                                    No successful sales credit yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
