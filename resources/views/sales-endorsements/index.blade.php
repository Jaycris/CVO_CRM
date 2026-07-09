<x-app-layout>
    <x-slot name="header">
        Sales Endorsement Form
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Sales Endorsement</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    View submitted endorsement details and create new sales endorsements.
                </p>
            </div>

            @if ($canSubmitEndorsement)
                <a href="{{ route('sales.endorsements.create') }}"
                   class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    + Endorse Sale
                </a>
            @endif
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
             x-data="{ selectedIds: [] }">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Sales Endorsement Directory</h2>
                        @if ($canDeleteEndorsements)
                            <p x-show="selectedIds.length > 0"
                               x-cloak
                               x-text="`${selectedIds.length} selected`"
                               class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-200"></p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($canDeleteEndorsements)
                            <form method="POST"
                                  action="{{ route('sales.endorsements.bulk-destroy') }}"
                                  x-on:submit="if (selectedIds.length === 0 || !confirm('Delete selected sales endorsement record(s)? This action cannot be undone.')) { $event.preventDefault(); }">
                                @csrf
                                @method('DELETE')

                                <template x-for="endorsementId in selectedIds" :key="`delete-${endorsementId}`">
                                    <input type="hidden" name="endorsement_ids[]" x-bind:value="endorsementId">
                                </template>

                                <button type="submit"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        title="Delete selected endorsements"
                                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border text-sm font-semibold shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        @endif

                        <form method="GET" action="{{ route('sales.endorsements.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search endorsements..."
                                   class="w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">

                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>

                            @if ($search !== '')
                                <a href="{{ route('sales.endorsements.index') }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @php $visibleEndorsementIds = $endorsements->pluck('id')->values(); @endphp
                <table class="w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            @if ($canDeleteEndorsements)
                                <th class="w-[4%] px-3 py-4">
                                    <input type="checkbox"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950"
                                           x-bind:checked="@js($visibleEndorsementIds).length > 0 && @js($visibleEndorsementIds).every((id) => selectedIds.includes(id))"
                                           x-on:change="$event.target.checked ? selectedIds = Array.from(new Set([...selectedIds, ...@js($visibleEndorsementIds)])) : selectedIds = selectedIds.filter((id) => !@js($visibleEndorsementIds).includes(id))">
                                </th>
                            @endif
                            <th class="w-[12%] px-3 py-4">SE ID</th>
                            <th class="w-[10%] px-3 py-4">Brand</th>
                            <th class="w-[11%] px-3 py-4">Agent</th>
                            <th class="w-[11%] px-3 py-4">Author</th>
                            <th class="w-[14%] px-3 py-4">Book Title</th>
                            <th class="w-[10%] px-3 py-4">Service</th>
                            <th class="w-[9%] px-3 py-4">Amount</th>
                            <th class="w-[10%] px-3 py-4">Payment</th>
                            @if ($isAdmin)
                                <th class="w-[10%] px-3 py-4">Contact</th>
                                <th class="w-[11%] px-3 py-4">Email</th>
                                <th class="w-[9%] px-3 py-4">ISBN</th>
                            @endif
                            <th class="w-[10%] px-3 py-4">Submitted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($endorsements as $endorsement)
                            @php
                                $brand = $endorsement->brand;
                                $brandName = $brand?->imprint_name ?? 'CreatiVision';
                                $brandPrimary = $brand?->primary_color ?: '#065f46';
                                $brandAccent = $brand?->accent_color ?: '#d1fae5';
                            @endphp
                            <tr @if ($canDeleteEndorsements)
                                    x-on:click="selectedIds.includes({{ $endorsement->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $endorsement->id }}) : selectedIds.push({{ $endorsement->id }})"
                                    x-bind:class="selectedIds.includes({{ $endorsement->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                    class="cursor-pointer align-top"
                                @else
                                    class="align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60"
                                @endif>
                                @if ($canDeleteEndorsements)
                                    <td class="px-3 py-4">
                                        <input type="checkbox"
                                               x-bind:checked="selectedIds.includes({{ $endorsement->id }})"
                                               x-on:click.stop="selectedIds.includes({{ $endorsement->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $endorsement->id }}) : selectedIds.push({{ $endorsement->id }})"
                                               class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                    </td>
                                @endif
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-amber-700 dark:text-amber-200">
                                    {{ $endorsement->endorsement_code }}
                                </td>
                                <td class="px-3 py-4">
                                    <span class="inline-flex max-w-[8rem] items-center rounded-full px-2.5 py-1 text-[11px] font-semibold leading-tight"
                                          style="background-color: {{ $brandAccent }}; color: {{ $brandPrimary }};"
                                          title="{{ $brandName }}">
                                        {{ \Illuminate\Support\Str::limit($brandName, 18) }}
                                    </span>
                                </td>
                                <td class="break-words px-3 py-4">
                                    <p class="font-semibold leading-snug text-slate-900 dark:text-zinc-100">
                                        {{ trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? '')) ?: 'Unknown' }}
                                    </p>
                                    @if ($endorsement->has_frankie)
                                        <p class="mt-1 break-words text-[11px] leading-tight text-slate-500 dark:text-zinc-400">
                                            Frankie: {{ $endorsement->frankie_agent_name }}
                                            @if ($endorsement->frankie_commission_percent)
                                                ({{ number_format((float) $endorsement->frankie_commission_percent, 0) }}%)
                                            @endif
                                        </p>
                                    @endif
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->author_name }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <span class="line-clamp-2" title="{{ $endorsement->book_title }}">{{ $endorsement->book_title }}</span>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->services }}</td>
                                <td class="px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">${{ number_format((float) $endorsement->amount, 2) }}</td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->payment }}</td>
                                @if ($isAdmin)
                                    <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->contact_number }}</td>
                                    <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                        <span class="block truncate" title="{{ $endorsement->email }}">{{ $endorsement->email }}</span>
                                    </td>
                                    <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->isbn }}</td>
                                @endif
                                <td class="px-3 py-4 leading-snug text-slate-500 dark:text-zinc-400">{{ $endorsement->created_at?->format('M d, Y') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($isAdmin ? 12 : 9) + ($canDeleteEndorsements ? 1 : 0) }}" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No sales endorsements yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($endorsements->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $endorsements->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
