<x-app-layout>
    <x-slot name="header">
        Finance
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Contracts</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    View clients sent for contract signing and clients who already signed.
                </p>
            </div>

            <div class="flex rounded-xl border border-slate-200 bg-white p-1 text-sm shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                <a href="{{ route('finance.contracts.index') }}"
                   class="{{ $status === 'all' ? 'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' : 'text-slate-600 dark:text-zinc-300' }} rounded-lg px-3 py-2 font-semibold">
                    All
                </a>
                <a href="{{ route('finance.contracts.index', ['status' => 'sent']) }}"
                   class="{{ $status === 'sent' ? 'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' : 'text-slate-600 dark:text-zinc-300' }} rounded-lg px-3 py-2 font-semibold">
                    Sent
                </a>
                <a href="{{ route('finance.contracts.index', ['status' => 'signed']) }}"
                   class="{{ $status === 'signed' ? 'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' : 'text-slate-600 dark:text-zinc-300' }} rounded-lg px-3 py-2 font-semibold">
                    Signed
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                {{ session('error') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
             x-data="{ selectedIds: [], endorseModalOpen: false }">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Contract Directory</h2>

                    <div class="flex flex-wrap items-center gap-2">
                    @if ($canManageContracts || $canDeleteContracts || $canEndorseProduction)
                        <div class="flex items-center gap-2">
                            @if ($canEndorseProduction)
                                <button type="button"
                                        x-on:click="if (selectedIds.length > 0) { endorseModalOpen = true }"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        class="inline-flex h-10 items-center justify-center rounded-xl border px-4 text-xs font-semibold shadow-sm">
                                    Endorse
                                </button>
                            @endif

                            @if ($canManageContracts)
                            <form method="POST" action="{{ route('finance.contracts.bulk-update') }}"
                                  x-on:submit="if (selectedIds.length === 0) { $event.preventDefault(); }">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <template x-for="endorsementId in selectedIds" :key="`sent-${endorsementId}`">
                                    <input type="hidden" name="endorsement_ids[]" :value="endorsementId">
                                </template>
                                <button type="submit"
                                        name="contract_status"
                                        value="sent"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        class="inline-flex h-10 items-center justify-center rounded-xl border px-4 text-xs font-semibold shadow-sm">
                                    Mark Sent
                                </button>
                            </form>

                            <form method="POST" action="{{ route('finance.contracts.bulk-update') }}"
                                  x-on:submit="if (selectedIds.length === 0) { $event.preventDefault(); }">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <template x-for="endorsementId in selectedIds" :key="`signed-${endorsementId}`">
                                    <input type="hidden" name="endorsement_ids[]" :value="endorsementId">
                                </template>
                                <button type="submit"
                                        name="contract_status"
                                        value="signed"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        class="inline-flex h-10 items-center justify-center rounded-xl border px-4 text-xs font-semibold shadow-sm">
                                    Mark Signed
                                </button>
                            </form>

                            @endif

                            @if ($canDeleteContracts)
                            <form method="POST" action="{{ route('finance.contracts.bulk-destroy') }}"
                                  x-on:submit="if (selectedIds.length === 0 || !confirm('Delete selected contract record(s)? This will clear their contract status only.')) { $event.preventDefault(); }">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <template x-for="endorsementId in selectedIds" :key="`delete-contract-${endorsementId}`">
                                    <input type="hidden" name="endorsement_ids[]" :value="endorsementId">
                                </template>
                                <button type="submit"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        title="Delete selected contract records"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border text-xs font-semibold shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                            @endif
                        </div>
                    @endif

                        <form method="GET" action="{{ route('finance.contracts.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="status" value="{{ $status }}">
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search contracts..."
                                   class="w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">

                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>

                            @if ($search !== '')
                                <a href="{{ route('finance.contracts.index', ['status' => $status]) }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div>
                @php $visibleContractIds = $endorsements->pluck('id')->values(); @endphp
                @php $canSelectContracts = $canManageContracts || $canDeleteContracts || $canEndorseProduction; @endphp
                <table class="w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            @if ($canSelectContracts)
                                <th class="w-[4%] px-3 py-4">
                                    <input type="checkbox"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950"
                                           x-bind:checked="@js($visibleContractIds).length > 0 && @js($visibleContractIds).every((id) => selectedIds.includes(id))"
                                           x-on:change="$event.target.checked ? selectedIds = Array.from(new Set([...selectedIds, ...@js($visibleContractIds)])) : selectedIds = selectedIds.filter((id) => !@js($visibleContractIds).includes(id))">
                                </th>
                            @endif
                            <th class="w-[10%] px-3 py-4">Brand</th>
                            <th class="w-[10%] px-3 py-4">Agent</th>
                            <th class="w-[12%] px-3 py-4">Author</th>
                            <th class="w-[16%] px-3 py-4">Book Title</th>
                            <th class="w-[11%] px-3 py-4">Service</th>
                            <th class="w-[9%] px-3 py-4">Amount</th>
                            <th class="w-[9%] px-3 py-4">Contract</th>
                            <th class="w-[10%] px-3 py-4">Production</th>
                            <th class="w-[9%] px-3 py-4">Sent</th>
                            <th class="w-[10%] px-3 py-4">Signed</th>
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
                            <tr @if ($canSelectContracts)
                                    x-on:click="selectedIds.includes({{ $endorsement->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $endorsement->id }}) : selectedIds.push({{ $endorsement->id }})"
                                    x-bind:class="selectedIds.includes({{ $endorsement->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                    class="cursor-pointer align-top"
                                @else
                                    class="align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60"
                                @endif>
                                @if ($canSelectContracts)
                                    <td class="px-3 py-4">
                                        <input type="checkbox"
                                               x-bind:checked="selectedIds.includes({{ $endorsement->id }})"
                                               x-on:click.stop="selectedIds.includes({{ $endorsement->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $endorsement->id }}) : selectedIds.push({{ $endorsement->id }})"
                                               class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
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
                                    {{ trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? '')) ?: 'Unknown' }}
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->author_name }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <span class="line-clamp-2" title="{{ $endorsement->book_title }}">{{ $endorsement->book_title }}</span>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->services }}</td>
                                <td class="px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">${{ number_format((float) $endorsement->amount, 2) }}</td>
                                <td class="px-3 py-4">
                                    <span @class([
                                        'rounded-full px-2 py-1 text-[11px] font-semibold',
                                        'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $endorsement->contract_status,
                                        'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200' => $endorsement->contract_status === 'sent',
                                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' => $endorsement->contract_status === 'signed',
                                    ])>
                                        {{ $endorsement->contract_status ? ucfirst($endorsement->contract_status) : 'Not Sent' }}
                                    </span>
                                </td>
                                <td class="px-3 py-4">
                                    @if ($endorsement->productionProject)
                                        <span class="rounded-full bg-sky-100 px-2 py-1 text-[11px] font-semibold text-sky-700 dark:bg-sky-400/15 dark:text-sky-200">
                                            {{ str($endorsement->productionProject->tracker_type)->title() }}
                                        </span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                            Not endorsed
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->contract_sent_at?->format('M d, Y') ?: '-' }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement->contract_signed_at?->format('M d, Y') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canSelectContracts ? 11 : 10 }}" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No contract records yet.
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

            @if ($canEndorseProduction)
                <div x-show="endorseModalOpen"
                     x-cloak
                     x-transition.opacity
                     class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/45 p-4"
                     x-on:click.self="endorseModalOpen = false">
                    <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Endorse to Production</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                    Add detailed production notes for <span x-text="selectedIds.length"></span> selected contract(s).
                                </p>
                            </div>

                            <button type="button"
                                    x-on:click="endorseModalOpen = false"
                                    class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        <form method="POST"
                              action="{{ route('finance.contracts.endorse-production') }}"
                              class="mt-6 space-y-5"
                              x-on:submit="if (selectedIds.length === 0 || !confirm('Endorse selected contract record(s) to Production?')) { $event.preventDefault(); }">
                            @csrf
                            <input type="hidden" name="status" value="{{ $status }}">
                            <template x-for="endorsementId in selectedIds" :key="`production-modal-${endorsementId}`">
                                <input type="hidden" name="endorsement_ids[]" :value="endorsementId">
                            </template>

                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Tracker</span>
                                <select name="tracker_type"
                                        required
                                        class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <option value="publishing">Publishing</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="events">Events</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Endorsement Notes</span>
                                <textarea name="endorsement_notes"
                                          rows="8"
                                          placeholder="Add full production instructions, reminders, client requests, and important notes..."
                                          class="mt-2 w-full resize-y rounded-xl border-slate-300 text-sm leading-6 shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500"></textarea>
                            </label>

                            <div class="flex justify-end gap-3">
                                <button type="button"
                                        x-on:click="endorseModalOpen = false"
                                        class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Cancel
                                </button>
                                <button type="submit"
                                        class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                    Endorse to Production
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
