<x-app-layout>
    <x-slot name="header">
        Finance
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $pageTitle }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $pageDescription }}</p>
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

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($canDeleteClients)
                            <form method="POST"
                                  action="{{ route('finance.clients.bulk-destroy') }}"
                                  x-on:submit="if (selectedIds.length === 0 || !confirm('Delete selected client record(s)? This will remove their finance payment record only.')) { $event.preventDefault(); }">
                                @csrf
                                @method('DELETE')

                                <template x-for="paymentId in selectedIds" :key="`client-delete-${paymentId}`">
                                    <input type="hidden" name="payment_ids[]" x-bind:value="paymentId">
                                </template>

                                <button type="submit"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        title="Delete selected client records"
                                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border text-sm font-semibold shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        @endif

                        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center gap-2">
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search clients..."
                                   class="w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">

                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>

                            @if ($search !== '')
                                <a href="{{ url()->current() }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @php $visiblePaymentIds = $payments->pluck('id')->values(); @endphp
                <table class="w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            @if ($canDeleteClients)
                                <th class="w-[4%] px-3 py-4">
                                    <input type="checkbox"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950"
                                           x-bind:checked="@js($visiblePaymentIds).length > 0 && @js($visiblePaymentIds).every((id) => selectedIds.includes(id))"
                                           x-on:change="$event.target.checked ? selectedIds = Array.from(new Set([...selectedIds, ...@js($visiblePaymentIds)])) : selectedIds = selectedIds.filter((id) => !@js($visiblePaymentIds).includes(id))">
                                </th>
                            @endif
                            <th class="w-[10%] px-3 py-4">Brand</th>
                            <th class="w-[12%] px-3 py-4">Agent</th>
                            <th class="w-[13%] px-3 py-4">Author</th>
                            <th class="w-[16%] px-3 py-4">Book Title</th>
                            <th class="w-[13%] px-3 py-4">Service</th>
                            <th class="w-[10%] px-3 py-4">Amount</th>
                            <th class="w-[12%] px-3 py-4">Payment Method</th>
                            <th class="w-[11%] px-3 py-4">Sold Date</th>
                            <th class="w-[12%] px-3 py-4">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($payments as $payment)
                            @php
                                $endorsement = $payment->endorsement;
                                $brand = $payment->brand ?? $endorsement?->brand;
                                $brandName = $brand?->imprint_name ?? 'CreatiVision';
                                $brandPrimary = $brand?->primary_color ?: '#065f46';
                                $brandAccent = $brand?->accent_color ?: '#d1fae5';
                            @endphp
                            <tr @if ($canDeleteClients)
                                    x-on:click="selectedIds.includes({{ $payment->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $payment->id }}) : selectedIds.push({{ $payment->id }})"
                                    x-bind:class="selectedIds.includes({{ $payment->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                    class="cursor-pointer align-top"
                                @else
                                    class="align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60"
                                @endif>
                                @if ($canDeleteClients)
                                    <td class="px-3 py-4">
                                        <input type="checkbox"
                                               x-bind:checked="selectedIds.includes({{ $payment->id }})"
                                               x-on:click.stop="selectedIds.includes({{ $payment->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $payment->id }}) : selectedIds.push({{ $payment->id }})"
                                               class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                    </td>
                                @endif
                                <td class="px-3 py-4">
                                    <span class="inline-flex max-w-[8rem] items-center rounded-full px-2.5 py-1 text-[11px] font-semibold leading-tight"
                                          style="background-color: {{ $brandAccent }}; color: {{ $brandPrimary }};"
                                          title="{{ $brandName }}">
                                        {{ \Illuminate\Support\Str::limit($brandName, 18) }}
                                    </span>
                                </td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">
                                    {{ trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: 'Unknown' }}
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement?->author_name ?: '-' }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <span class="line-clamp-2" title="{{ $endorsement?->book_title }}">{{ $endorsement?->book_title ?: '-' }}</span>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement?->services ?: '-' }}</td>
                                <td class="px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">${{ number_format((float) ($endorsement?->amount ?? 0), 2) }}</td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $payment->payment_method }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $payment->sold_date?->format('M d, Y') ?: '-' }}</td>
                                <td class="px-3 py-4">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' => $payment->status === 'Payment Success',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200' => $payment->status === 'Refund',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200' => $payment->status === 'Dispute',
                                    ])>
                                        {{ $payment->status === 'Payment Success' ? 'Paid' : $payment->status }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canDeleteClients ? 10 : 9 }}" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    {{ $emptyMessage }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $payments->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
