<x-app-layout>
    <x-slot name="header">
        Commission Settings
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Commission Settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Control shared Frankie sales, PHP conversion, and card payment holds.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="max-w-3xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <form method="POST" action="{{ route('admin.commission-settings.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="frankie_commission_percent" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Frankie Agent Share <span class="text-rose-600">*</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <input id="frankie_commission_percent"
                               name="frankie_commission_percent"
                               type="number"
                               min="0"
                               max="100"
                               step="0.01"
                               value="{{ old('frankie_commission_percent', $frankiePercent) }}"
                               required
                               class="w-40 rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <span class="text-sm font-semibold text-slate-600 dark:text-zinc-300">%</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        Example: 50% means the main agent receives 50% credit and the Frankie agent receives 50% credit.
                    </p>
                    <x-input-error :messages="$errors->get('frankie_commission_percent')" class="mt-2" />
                </div>

                <div>
                    <label for="commission_exchange_rate" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        USD to PHP Exchange Rate <span class="text-rose-600">*</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <span class="text-sm font-semibold text-slate-600 dark:text-zinc-300">$1 = ₱</span>
                        <input id="commission_exchange_rate"
                               name="commission_exchange_rate"
                               type="number"
                               min="0.01"
                               step="0.0001"
                               value="{{ old('commission_exchange_rate', $exchangeRate) }}"
                               required
                               class="w-44 rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        Update this manually when the bank rate changes. New commission calculations and HRIS exports will use this saved rate.
                    </p>
                    <x-input-error :messages="$errors->get('commission_exchange_rate')" class="mt-2" />
                </div>

                <div>
                    <label for="card_payment_hold_percent" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Card Payment Hold <span class="text-rose-600">*</span>
                    </label>
                    <div class="flex items-center gap-3">
                        <input id="card_payment_hold_percent"
                               name="card_payment_hold_percent"
                               type="number"
                               min="0"
                               max="100"
                               step="0.01"
                               value="{{ old('card_payment_hold_percent', $cardHoldPercent) }}"
                               required
                               class="w-40 rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <span class="text-sm font-semibold text-slate-600 dark:text-zinc-300">%</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        This hold is applied only to commission rows where the payment method is Card.
                    </p>
                    <x-input-error :messages="$errors->get('card_payment_hold_percent')" class="mt-2" />
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    The Frankie share is saved into new sales endorsements when they are submitted. The exchange rate and card hold are read when commission totals are calculated.
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-400 dark:text-zinc-950">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
