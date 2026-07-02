<x-app-layout>
    <x-slot name="header">
        Payment Records
    </x-slot>

    <div class="space-y-6"
         x-data="{
            selectedIds: [],
            selectedPayment: {},
            paymentModalOpen: false,
            createModalOpen: false,
            endorsementDropdownOpen: false,
            selectedEndorsementId: @js(old('sales_endorsement_id', '')),
            selectedEndorsementText: '',
            endorsementOptions: @js($endorsements->map(fn ($endorsement) => [
                'id' => $endorsement->id,
                'code' => $endorsement->endorsement_code,
                'author' => $endorsement->author_name,
                'bookTitle' => $endorsement->book_title,
                'agent' => trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? '')) ?: 'Unknown',
            ])->values()->all()),
            filteredEndorsementOptions() {
                const search = this.selectedEndorsementText.trim().toLowerCase();

                if (!search) {
                    return this.endorsementOptions.slice(0, 8);
                }

                return this.endorsementOptions
                    .filter((item) => {
                        return item.code.toLowerCase().includes(search)
                            || item.author.toLowerCase().includes(search)
                            || item.bookTitle.toLowerCase().includes(search)
                            || item.agent.toLowerCase().includes(search);
                    })
                    .slice(0, 8);
            },
            selectEndorsement(endorsement) {
                this.selectedEndorsementId = endorsement.id;
                this.selectedEndorsementText = `${endorsement.code} - ${endorsement.author}`;
                this.endorsementDropdownOpen = false;
            },
            clearSelectedEndorsement() {
                this.selectedEndorsementId = '';
            },
            togglePayment(payment) {
                if (this.selectedIds.includes(payment.id)) {
                    this.selectedIds = this.selectedIds.filter((id) => id !== payment.id);
                    return;
                }

                this.selectedIds.push(payment.id);
            },
            openPaymentModal(payment) {
                this.selectedPayment = payment;
                this.paymentModalOpen = true;
            }
         }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Payment Records</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Manually add and track payment method, sold date, and payment status.
                </p>
            </div>

            @if ($canManagePayments)
                <button type="button"
                        x-on:click="createModalOpen = true"
                        class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    + Add Payment
                </button>
            @endif
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                Please check the form and try again.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Sales Payment Directory</h2>
                        <p x-show="selectedIds.length > 0"
                           x-cloak
                           x-text="`${selectedIds.length} selected`"
                           class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-200"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @if ($canDeletePayments)
                            <form method="POST"
                                  action="{{ route('finance.payments.bulk-destroy') }}"
                                  x-on:submit="if (selectedIds.length === 0 || !confirm('Delete selected payment record(s)? This action cannot be undone.')) { $event.preventDefault(); }">
                                @csrf
                                @method('DELETE')

                                <template x-for="paymentId in selectedIds" :key="`payment-delete-${paymentId}`">
                                    <input type="hidden" name="payment_ids[]" x-bind:value="paymentId">
                                </template>

                                <button type="submit"
                                        x-bind:disabled="selectedIds.length === 0"
                                        x-bind:class="selectedIds.length > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        title="Delete selected payment records"
                                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border text-sm font-semibold shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        @endif

                        <form method="GET" action="{{ route('finance.payments.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search SE ID, author, payment..."
                                   class="w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">

                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>

                            @if ($search !== '')
                                <a href="{{ route('finance.payments.index') }}"
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
                            <th class="w-[4%] px-3 py-4">
                                @if ($canDeletePayments)
                                    <input type="checkbox"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950"
                                           x-bind:checked="@js($visiblePaymentIds).length > 0 && @js($visiblePaymentIds).every((id) => selectedIds.includes(id))"
                                           x-on:change="$event.target.checked ? selectedIds = Array.from(new Set([...selectedIds, ...@js($visiblePaymentIds)])) : selectedIds = selectedIds.filter((id) => !@js($visiblePaymentIds).includes(id))">
                                @endif
                            </th>
                            <th class="w-[12%] px-3 py-4">SE ID</th>
                            <th class="w-[10%] px-3 py-4">Brand</th>
                            <th class="w-[11%] px-3 py-4">Agent</th>
                            <th class="w-[11%] px-3 py-4">Author</th>
                            <th class="w-[14%] px-3 py-4">Book Title</th>
                            <th class="w-[9%] px-3 py-4">Amount</th>
                            <th class="w-[12%] px-3 py-4">Payment Method</th>
                            <th class="w-[10%] px-3 py-4">Sold Date</th>
                            <th class="w-[10%] px-3 py-4">Status</th>
                            @if ($canManagePayments)
                                <th class="w-[7%] px-3 py-4 text-right">Action</th>
                            @endif
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
                                $paymentPayload = [
                                    'id' => $payment->id,
                                    'updateUrl' => route('finance.payments.update', $payment),
                                    'paymentMethod' => old('payment_method', $payment->payment_method) ?? '',
                                    'soldDate' => old('sold_date', $payment->sold_date?->format('Y-m-d')) ?? '',
                                    'status' => old('status', $payment->status) ?? '',
                                ];
                            @endphp
                            <tr x-on:click="togglePayment(@js($paymentPayload))"
                                x-bind:class="selectedIds.includes({{ $payment->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                class="cursor-pointer align-top">
                                <td class="px-3 py-4">
                                    <input type="checkbox"
                                           x-bind:checked="selectedIds.includes({{ $payment->id }})"
                                           x-on:click.stop="togglePayment(@js($paymentPayload))"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-amber-700 dark:text-amber-200">{{ $endorsement?->endorsement_code }}</td>
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
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement?->author_name }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <span class="line-clamp-2" title="{{ $endorsement?->book_title }}">{{ $endorsement?->book_title }}</span>
                                </td>
                                <td class="px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">${{ number_format((float) ($endorsement?->amount ?? 0), 2) }}</td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-700 dark:text-zinc-200">{{ $payment->payment_method }}</td>
                                <td class="px-3 py-4 font-semibold leading-snug text-slate-700 dark:text-zinc-200">{{ $payment->sold_date?->format('M d, Y') }}</td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-700 dark:text-zinc-200">{{ $payment->status }}</td>
                                @if ($canManagePayments)
                                    <td class="px-3 py-4 text-right">
                                        <button type="button"
                                                x-on:click.stop="openPaymentModal(@js($paymentPayload))"
                                                title="Edit payment"
                                                class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                            </svg>
                                        </button>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManagePayments ? 11 : 10 }}" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No payment records yet.
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

        @if ($canManagePayments)
            <div x-show="createModalOpen"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/45 p-4"
                 x-on:click.self="createModalOpen = false">
                <div class="w-full max-w-3xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Add Payment Record</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                Select the Sales Endorsement ID, then add payment details.
                            </p>
                        </div>

                        <button type="button"
                                x-on:click="createModalOpen = false"
                                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('finance.payments.store') }}" class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                        @csrf

                        <label class="relative block md:col-span-2" x-on:click.outside="endorsementDropdownOpen = false">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Sales Endorsement <span class="text-rose-500">*</span></span>
                            <input type="hidden" name="sales_endorsement_id" x-bind:value="selectedEndorsementId">
                            <div class="relative mt-2">
                                <input type="text"
                                       x-model="selectedEndorsementText"
                                       x-on:focus="endorsementDropdownOpen = true"
                                       x-on:input.debounce.150ms="endorsementDropdownOpen = true; clearSelectedEndorsement()"
                                       x-on:keydown.escape.prevent="endorsementDropdownOpen = false"
                                       placeholder="Search SE ID, author, book title, or agent"
                                       autocomplete="off"
                                       required
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 pr-11 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <button type="button"
                                        x-on:click="endorsementDropdownOpen = !endorsementDropdownOpen"
                                        class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-300"
                                        aria-label="Show sales endorsement suggestions">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': endorsementDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                    </svg>
                                </button>
                            </div>

                            <div x-show="endorsementDropdownOpen"
                                 x-cloak
                                 x-transition
                                 class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                                <template x-for="endorsement in filteredEndorsementOptions()" :key="endorsement.id">
                                    <button type="button"
                                            x-on:click="selectEndorsement(endorsement)"
                                            class="block w-full rounded-lg px-3 py-2 text-left hover:bg-amber-50 dark:hover:bg-amber-400/10">
                                        <span class="block text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                            <span x-text="endorsement.code"></span>
                                            <span> - </span>
                                            <span x-text="endorsement.author"></span>
                                        </span>
                                        <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-zinc-400">
                                            <span x-text="endorsement.bookTitle"></span>
                                            <span> | Agent: </span>
                                            <span x-text="endorsement.agent"></span>
                                        </span>
                                    </button>
                                </template>

                                <div x-show="filteredEndorsementOptions().length === 0" class="px-3 py-4 text-sm text-slate-500 dark:text-zinc-400">
                                    No matching sales endorsement found.
                                </div>
                            </div>
                            <x-input-error :messages="$errors->get('sales_endorsement_id')" class="mt-2" />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Payment Method <span class="text-rose-500">*</span></span>
                            <select name="payment_method"
                                    required
                                    class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">Select</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Sold Date <span class="text-rose-500">*</span></span>
                            <x-date-picker name="sold_date" :value="old('sold_date')" required
                                           class="mt-2 w-full rounded-xl border-slate-300 px-4 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                            <x-input-error :messages="$errors->get('sold_date')" class="mt-2" />
                        </label>

                        <label class="block md:col-span-2">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Status <span class="text-rose-500">*</span></span>
                            <select name="status"
                                    required
                                    class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">Select</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(old('status') === $status)>{{ $status }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('status')" class="mt-2" />
                        </label>

                        <div class="flex justify-end gap-3 md:col-span-2">
                            <button type="button"
                                    x-on:click="createModalOpen = false"
                                    class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Save Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="paymentModalOpen"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/45 p-4"
                 x-on:click.self="paymentModalOpen = false">
                <div class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Edit Payment Record</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                Update the selected payment details.
                            </p>
                        </div>

                        <button type="button"
                                x-on:click="paymentModalOpen = false"
                                class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST"
                          x-bind:action="selectedPayment.updateUrl"
                          class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
                        @csrf
                        @method('PUT')

                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Payment Method</span>
                            <select name="payment_method"
                                    x-model="selectedPayment.paymentMethod"
                                    required
                                    class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">Select</option>
                                @foreach ($paymentMethods as $method)
                                    <option value="{{ $method }}">{{ $method }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Sold Date</span>
                            <x-date-picker name="sold_date" x-model="selectedPayment.soldDate" required
                                           class="mt-2 w-full rounded-xl border-slate-300 px-4 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                        </label>

                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Status</span>
                            <select name="status"
                                    x-model="selectedPayment.status"
                                    required
                                    class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">Select</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}">{{ $status }}</option>
                                @endforeach
                            </select>
                        </label>

                        <div class="flex justify-end gap-3 md:col-span-3">
                            <button type="button"
                                    x-on:click="paymentModalOpen = false"
                                    class="rounded-xl px-5 py-3 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Save Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
