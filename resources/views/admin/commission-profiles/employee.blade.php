<x-app-layout>
    @php
        $fullName = trim($user->first_name . ' ' . $user->last_name);
        $targetAmount = old('target', $target?->amount ?? 0);
        $formattedTargetAmount = number_format((float) str_replace(',', '', (string) $targetAmount), 2);
        $selectedProfileId = old('commission_profile_id', $user->commission_profile_id);
        $markupPercent = old('markup_commission_percent', $user->markup_commission_percent ?? 50);
        $thresholdAmount = old('commission_threshold_amount', $user->commission_threshold_amount ?? 500);
        $isExempt = (bool) old('is_commission_threshold_exempt', $user->is_commission_threshold_exempt);
        $isCommissionEligible = (bool) old('is_commission_eligible', $user->is_commission_eligible);
        $currentProfile = $profiles->firstWhere('id', (int) $selectedProfileId) ?? $profiles->firstWhere('is_default', true) ?? $profiles->first();
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-slate-800 leading-tight dark:text-zinc-100">
            Employee Commission Profile
        </h2>
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-950 dark:text-zinc-100">{{ $fullName }}</h1>
                <p class="mt-1 text-slate-500 dark:text-zinc-400">
                    Manage this employee's monthly target, commission profile, markup percentage, and threshold.
                </p>
            </div>

            <a href="{{ route('admin.users.index') }}"
               class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Back to Users
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-5 py-4 text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                Please check the form and try again.
            </div>
        @endif

        <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
            <form method="POST"
                  action="{{ route('admin.users.commission-profile.update', $user) }}"
                  x-data="{ editing: false, exempt: @js($isExempt), commissionEligible: @js($isCommissionEligible) }"
                  class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                @csrf
                @method('PUT')

                <div class="border-b border-slate-200 px-6 py-5 dark:border-zinc-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-bold text-slate-950 dark:text-zinc-100">Profile Details</h2>
                            <p class="text-sm text-slate-500 dark:text-zinc-400">View this employee's current commission setup.</p>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <button type="button"
                                    x-show="!editing"
                                    x-on:click="editing = true"
                                    class="rounded-xl px-5 py-3 text-sm font-semibold text-white shadow-sm"
                                    style="background: var(--brand-primary);">
                                Edit Commission Profile
                            </button>

                            <button type="button"
                                    x-show="editing"
                                    x-cloak
                                    x-on:click="editing = false"
                                    class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                Cancel
                            </button>

                            <button type="submit"
                                    x-show="editing"
                                    x-cloak
                                    class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:ring-offset-zinc-900"
                                    style="background-color: #065f46; color: #ffffff;">
                                Save Profile
                            </button>
                        </div>
                    </div>
                </div>

                <div class="grid gap-5 p-6 lg:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-5 dark:bg-zinc-950">
                        <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Employee</p>
                        <p class="mt-2 text-lg font-bold text-slate-950 dark:text-zinc-100">{{ $fullName }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            {{ $user->role?->name ?? 'No role' }} / {{ $user->brand?->imprint_name ?? 'CreatiVision Outsourcing' }}
                        </p>
                        <p class="mt-3 inline-flex rounded-full px-3 py-1 text-xs font-bold {{ $isCommissionEligible ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200' : 'bg-slate-200 text-slate-600 dark:bg-zinc-800 dark:text-zinc-300' }}">
                            {{ $isCommissionEligible ? 'Commission Eligible' : 'Not Commission Eligible' }}
                        </p>
                    </div>

                    <div class="rounded-2xl bg-slate-50 p-5 dark:bg-zinc-950">
                        <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Work Arrangement</p>
                        <p class="mt-2 text-lg font-bold text-slate-950 dark:text-zinc-100">
                            {{ match($user->work_type) {
                                'remote' => 'Remote',
                                'hybrid' => 'Hybrid',
                                'site' => 'On-site',
                                default => 'Not set',
                            } }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Set in the user record.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Month</label>
                        <input type="month" name="month" value="{{ old('month', $month) }}"
                               x-bind:disabled="!editing"
                               class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] disabled:bg-slate-100 disabled:text-slate-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Agent Target</label>
                        <input type="text" inputmode="decimal" name="target" value="{{ $formattedTargetAmount }}"
                               x-bind:disabled="!editing"
                               class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] disabled:bg-slate-100 disabled:text-slate-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                    </div>

                    <div class="lg:col-span-2">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Commission Scheme</label>
                        <select name="commission_profile_id"
                                x-bind:disabled="!editing"
                                class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] disabled:bg-slate-100 disabled:text-slate-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                            <option value="">Use default scheme</option>
                            @foreach ($profiles as $profile)
                                <option value="{{ $profile->id }}" @selected((string) $selectedProfileId === (string) $profile->id)>
                                    {{ $profile->name }}{{ $profile->is_default ? ' (Default)' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Markup %</label>
                        <div class="mt-2 flex items-center gap-2">
                            <input type="number" min="0" max="100" step="0.01" name="markup_commission_percent" value="{{ $markupPercent }}"
                                   x-bind:disabled="!editing"
                                   class="w-full rounded-xl border-slate-300 shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] disabled:bg-slate-100 disabled:text-slate-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                            <span class="text-sm font-semibold text-slate-500 dark:text-zinc-400">%</span>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 dark:text-zinc-200">Threshold</label>
                        <input type="number" min="0" step="0.01" name="commission_threshold_amount" value="{{ $thresholdAmount }}"
                               x-show="!exempt"
                               x-bind:disabled="!editing || exempt"
                               class="mt-2 w-full rounded-xl border-slate-300 shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] disabled:bg-slate-100 disabled:text-slate-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:disabled:bg-zinc-800">
                        <p x-show="exempt" class="mt-2 rounded-xl bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-200">
                            This employee is exempt from the monthly threshold.
                        </p>
                    </div>

                    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-zinc-800 lg:col-span-2">
                        <input type="hidden" name="is_commission_eligible" value="0">
                        <input type="checkbox" name="is_commission_eligible" value="1"
                               x-model="commissionEligible"
                               x-bind:disabled="!editing"
                               class="rounded border-slate-300 text-[var(--brand-primary)] shadow-sm focus:ring-[var(--brand-primary)] disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-950"
                               @checked($isCommissionEligible)>
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Eligible for Sales Commission</span>
                            <span class="block text-sm text-slate-500 dark:text-zinc-400">If checked, this employee can submit credited sales and mirror commission setup to HRIS.</span>
                        </span>
                    </label>

                    <label class="flex items-center gap-3 rounded-2xl border border-slate-200 p-4 dark:border-zinc-800 lg:col-span-2">
                        <input type="hidden" name="is_commission_threshold_exempt" value="0">
                        <input type="checkbox" name="is_commission_threshold_exempt" value="1"
                               x-model="exempt"
                               x-bind:disabled="!editing"
                               class="rounded border-slate-300 text-[var(--brand-primary)] shadow-sm focus:ring-[var(--brand-primary)] disabled:opacity-60 dark:border-zinc-700 dark:bg-zinc-950"
                               @checked($isExempt)>
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Exempt from threshold</span>
                            <span class="block text-sm text-slate-500 dark:text-zinc-400">If checked, the monthly threshold deduction will not apply to this employee.</span>
                        </span>
                    </label>

                    <p x-show="!commissionEligible"
                       x-cloak
                       class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100 lg:col-span-2">
                        This setup is saved, but the employee is not active for sales commission until eligibility is enabled.
                    </p>
                </div>
            </form>

            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                    <h2 class="text-lg font-bold text-slate-950 dark:text-zinc-100">Current Commission Rules</h2>
                    @if ($isCommissionEligible)
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">{{ $currentProfile?->name ?? 'No profile selected' }}</p>

                        <div class="mt-5 space-y-3">
                            @forelse (($currentProfile?->rules ?? collect()) as $rule)
                                <div class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-3 text-sm dark:bg-zinc-950">
                                    <span class="font-semibold text-slate-700 dark:text-zinc-200">
                                        {{ rtrim(rtrim(number_format($rule->minimum_mtd_percent, 2), '0'), '.') }}% MTD
                                    </span>
                                    <span class="font-bold text-emerald-700 dark:text-emerald-200">
                                        {{ rtrim(rtrim(number_format($rule->commission_percent, 2), '0'), '.') }}%
                                    </span>
                                </div>
                            @empty
                                <p class="rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                                    No commission rules available.
                                </p>
                            @endforelse
                        </div>
                    @else
                        <p class="mt-5 rounded-xl bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-600 dark:bg-zinc-950 dark:text-zinc-300">
                            Not active for commission.
                        </p>
                    @endif
                </div>

                <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    Threshold applies once per month. The system deducts it from service first, then markup if the service amount does not cover it.
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
