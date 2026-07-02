<x-app-layout>
    <x-slot name="header">
        Leads
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $pageDescription }}</p>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($summaryCards as $card)
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $card['count'] }}</h3>
                    <p @class([
                        'mt-2 text-sm',
                        'text-amber-600 dark:text-amber-300' => $card['tone'] === 'amber',
                        'text-rose-600 dark:text-rose-300' => $card['tone'] === 'rose',
                        'text-emerald-600 dark:text-emerald-300' => $card['tone'] === 'emerald',
                        'text-sky-600 dark:text-sky-300' => $card['tone'] === 'sky',
                        'text-violet-600 dark:text-violet-300' => $card['tone'] === 'violet',
                        'text-slate-600 dark:text-zinc-300' => $card['tone'] === 'slate',
                    ])>
                        {{ $card['hint'] }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
             x-data="{ selectedIds: [] }">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $pageTitle }} Directory</h2>
                        <p x-show="selectedIds.length > 0"
                           x-cloak
                           x-text="`${selectedIds.length} selected`"
                           class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-200"></p>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="w-[4%] px-3 py-4"></th>
                            <th class="w-[15%] px-3 py-4">Author</th>
                            <th class="w-[21%] px-3 py-4">Book Title</th>
                            <th class="w-[14%] px-3 py-4">Service</th>
                            <th class="w-[11%] px-3 py-4">Amount</th>
                            <th class="w-[13%] px-3 py-4">Payment Method</th>
                            <th class="w-[11%] px-3 py-4">Sold Date</th>
                            <th class="w-[7%] px-3 py-4">Status</th>
                            <th class="w-[7%] px-3 py-4">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($endorsements as $endorsement)
                            <tr x-on:click="selectedIds.includes({{ $endorsement->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $endorsement->id }}) : selectedIds.push({{ $endorsement->id }})"
                                x-bind:class="selectedIds.includes({{ $endorsement->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                class="cursor-pointer align-top">
                                <td class="px-3 py-4">
                                    <input type="checkbox"
                                           x-bind:checked="selectedIds.includes({{ $endorsement->id }})"
                                           x-on:click.stop="selectedIds.includes({{ $endorsement->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $endorsement->id }}) : selectedIds.push({{ $endorsement->id }})"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->author_name }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <span class="line-clamp-2" title="{{ $endorsement->book_title }}">{{ $endorsement->book_title }}</span>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->services }}</td>
                                <td class="px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">${{ number_format((float) $endorsement->amount, 2) }}</td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->paymentRecord?->payment_method ?: '-' }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->paymentRecord?->sold_date?->format('M d, Y') ?: '-' }}</td>
                                <td class="px-3 py-4">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' => $paymentStatus === 'Payment Success',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200' => $paymentStatus === 'Refund',
                                    ])>
                                        {{ $paymentStatus === 'Payment Success' ? 'Sold' : $paymentStatus }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 leading-snug text-slate-500 dark:text-zinc-400">{{ $endorsement->created_at?->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-md">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h.01M3 12h.01M3 18h.01" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-4 font-semibold text-slate-900 dark:text-zinc-100">No {{ strtolower($pageTitle) }} yet</h3>
                                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                            Matching records from Payment Records will show here.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($endorsements->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $endorsements->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
