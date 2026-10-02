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

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-sm text-rose-700 dark:border-rose-400/20 dark:bg-rose-400/10 dark:text-rose-200">
                Please check the chargeback form and try again.
            </div>
        @endif

        @if ($canManageChargebacks ?? false)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800" x-data="{ open: @js($errors->any()) }">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Record Chargeback</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Use this for a chargeback that must deduct from an agent's commission, including old sales that were never recorded as CRM payments.
                        </p>
                    </div>
                    <button type="button"
                            x-on:click="open = !open"
                            class="rounded-xl bg-[var(--brand-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                        <span x-text="open ? 'Hide Form' : 'Add Chargeback'"></span>
                    </button>
                </div>

                <form x-show="open" x-cloak method="POST" action="{{ route('reports.sales-activity.chargebacks.store') }}" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @csrf

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Brand</span>
                        <select name="brand_id" required class="mt-2 h-12 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="">Select brand</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected((int) old('brand_id') === (int) $brand->id)>{{ $brand->imprint_name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Agent</span>
                        <select name="agent_id" required class="mt-2 h-12 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="">Select agent</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((int) old('agent_id') === (int) $agent->id)>
                                    {{ trim($agent->first_name.' '.$agent->last_name) }} / {{ $agent->brand?->imprint_name ?? 'No brand' }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('agent_id')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Chargeback Amount</span>
                        <input name="amount" type="number" min="0.01" step="0.01" value="{{ old('amount') }}" required class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Chargeback Date</span>
                        <input name="chargeback_date" type="date" value="{{ old('chargeback_date', now()->toDateString()) }}" required class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('chargeback_date')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Original Sold Date</span>
                        <input name="original_sold_date" type="date" value="{{ old('original_sold_date') }}" class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('original_sold_date')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">SE ID / Reference</span>
                        <input name="endorsement_code" value="{{ old('endorsement_code') }}" placeholder="Optional" class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('endorsement_code')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Author Name</span>
                        <input name="author_name" value="{{ old('author_name') }}" required class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('author_name')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Book Title</span>
                        <input name="book_title" value="{{ old('book_title') }}" class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('book_title')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Service</span>
                        <input name="service_name" value="{{ old('service_name') }}" placeholder="Optional" class="mt-2 h-12 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('service_name')" class="mt-2" />
                    </label>

                    <label class="block">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Payment Method</span>
                        <select name="payment_method" class="mt-2 h-12 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="">Not specified</option>
                            @foreach (['Wire Payment', 'Invoice', 'Check Payment', 'Card'] as $method)
                                <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ $method }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                    </label>

                    <label class="block md:col-span-2">
                        <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Reason / Notes</span>
                        <textarea name="chargeback_reason" rows="3" class="mt-2 w-full rounded-xl border-slate-300 px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('chargeback_reason') }}</textarea>
                        <x-input-error :messages="$errors->get('chargeback_reason')" class="mt-2" />
                    </label>

                    <div class="flex items-end justify-end xl:col-span-3">
                        <button type="submit" class="rounded-xl bg-rose-700 px-6 py-3 text-sm font-bold text-white shadow-sm hover:bg-rose-800">
                            Record Chargeback
                        </button>
                    </div>
                </form>
            </section>
        @endif

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
                            <th class="px-6 py-4">Type</th>
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
                                $isChargeback = $activity->activity_type === 'chargeback';
                                $agentName = trim(($activity->agent?->first_name ?? '') . ' ' . ($activity->agent?->last_name ?? '')) ?: '-';
                                $frankieName = trim(($activity->frankieAgent?->first_name ?? '') . ' ' . ($activity->frankieAgent?->last_name ?? ''));
                                $minerName = trim(($activity->leadMiner?->first_name ?? '') . ' ' . ($activity->leadMiner?->last_name ?? '')) ?: '-';
                                $verifierName = trim(($activity->verifier?->first_name ?? '') . ' ' . ($activity->verifier?->last_name ?? '')) ?: '-';
                                $statusClasses = match ($activity->payment_status) {
                                    'Payment Success' => $isChargeback
                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200'
                                        : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200',
                                    'Refund' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200',
                                    'Dispute' => 'bg-amber-100 text-amber-700 dark:bg-amber-400/10 dark:text-amber-200',
                                    default => 'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300',
                                };
                            @endphp
                            <tr class="align-top hover:bg-slate-50/70 dark:hover:bg-zinc-800/60">
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $activity->sold_date?->format('M d, Y') ?? '-' }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $activity->endorsement_code ?? '-' }}</td>
                                <td class="px-6 py-4">
                                    <span @class([
                                        'inline-flex rounded-full px-3 py-1 text-xs font-semibold',
                                        'bg-rose-100 text-rose-700 dark:bg-rose-400/10 dark:text-rose-200' => $isChargeback,
                                        'bg-slate-100 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $isChargeback,
                                    ])>
                                        {{ $isChargeback ? 'Chargeback' : 'Sale' }}
                                    </span>
                                    @if ($isChargeback && $activity->original_sold_date)
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Original: {{ $activity->original_sold_date->format('M d, Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $activity->brand?->imprint_name ?? '-' }}</td>
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-zinc-100">
                                    <p>{{ $agentName }}</p>
                                    <p @class([
                                        'mt-1 text-xs',
                                        'text-rose-600 dark:text-rose-300' => $isChargeback,
                                        'text-slate-500 dark:text-zinc-400' => ! $isChargeback,
                                    ])>
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
                                <td @class([
                                    'px-6 py-4 font-semibold',
                                    'text-rose-600 dark:text-rose-300' => $isChargeback,
                                    'text-slate-900 dark:text-zinc-100' => ! $isChargeback,
                                ])>
                                    ${{ number_format((float) $activity->amount, 2) }}
                                    <p class="mt-1 text-xs font-normal text-slate-500 dark:text-zinc-400">{{ $isChargeback ? 'Commission deduction' : 'Gross sale' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                        {{ $isChargeback ? 'Chargeback' : ($activity->payment_status ?? 'Recorded') }}
                                    </span>
                                    @if ($isChargeback && $activity->chargeback_reason)
                                        <p class="mt-2 max-w-48 text-xs text-slate-500 dark:text-zinc-400">{{ $activity->chargeback_reason }}</p>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-6 py-16 text-center text-slate-500 dark:text-zinc-400">
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
