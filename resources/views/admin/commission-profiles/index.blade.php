<x-app-layout>
    <x-slot name="header">
        Commission Profiles
    </x-slot>

    <style>
        .commission-rule-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) auto;
            gap: 0.75rem;
            align-items: end;
        }

        .commission-rule-remove {
            min-width: 6.5rem;
            white-space: nowrap;
        }

        @media (max-width: 768px) {
            .commission-rule-row {
                grid-template-columns: 1fr;
            }

            .commission-rule-remove {
                width: 100%;
            }
        }
    </style>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Commission Profiles</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Manage service commission tiers, employee targets, markup rates, and monthly threshold rules.
            </p>
            <p class="mt-2 max-w-3xl text-sm text-slate-500 dark:text-zinc-400">
                The threshold applies once per employee each month. It deducts from Service MTD first, then from Markup MTD if the sale has no service amount or the service amount does not cover the full threshold.
            </p>
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

        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 p-6 dark:border-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Service Commission Rules</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Create reusable profiles. The system picks the highest rule the agent reaches for the selected month.
                </p>
            </div>

            <div class="grid gap-6 p-6 lg:grid-cols-[0.9fr_1.1fr]">
                <form method="POST" action="{{ route('admin.commission-profiles.store') }}" class="rounded-2xl border border-slate-200 bg-slate-50 p-5 dark:border-zinc-800 dark:bg-zinc-950/60" data-rule-form>
                    @csrf

                    <h3 class="text-base font-bold text-slate-900 dark:text-zinc-100">Create Profile</h3>

                    <div class="mt-4 space-y-4">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-zinc-300">Profile Name <span class="text-rose-600">*</span></label>
                            <input name="name" value="{{ old('name') }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700 dark:text-zinc-300">Description</label>
                            <textarea name="description" rows="3" class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('description') }}</textarea>
                        </div>

                        <label class="flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-zinc-300">
                            <input type="checkbox" name="is_default" value="1" class="rounded border-slate-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">
                            Make this the default service profile
                        </label>

                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-bold text-slate-900 dark:text-zinc-100">Tier Rules</p>
                                <button type="button" class="rounded-xl border border-[var(--brand-primary)] px-3 py-2 text-xs font-bold text-[var(--brand-primary)] hover:bg-[var(--brand-soft)]" data-add-rule>
                                    + Add Rule
                                </button>
                            </div>

                            <div class="space-y-2" data-rules>
                                <div class="commission-rule-row rounded-2xl border border-slate-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950" data-rule-row>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Minimum MTD %</span>
                                        <input type="number" name="rules[0][minimum_mtd_percent]" value="0" min="0" step="0.01" placeholder="Example: 0" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Commission %</span>
                                        <input type="number" name="rules[0][commission_percent]" value="15" min="0" max="100" step="0.01" placeholder="Example: 15" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    </label>
                                    <button type="button" class="commission-rule-remove rounded-xl border border-rose-200 px-4 py-3 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-400/30 dark:hover:bg-rose-400/10" data-remove-rule>Remove</button>
                                </div>
                                <div class="commission-rule-row rounded-2xl border border-slate-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950" data-rule-row>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Minimum MTD %</span>
                                        <input type="number" name="rules[1][minimum_mtd_percent]" value="75" min="0" step="0.01" placeholder="Example: 75" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Commission %</span>
                                        <input type="number" name="rules[1][commission_percent]" value="20" min="0" max="100" step="0.01" placeholder="Example: 20" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    </label>
                                    <button type="button" class="commission-rule-remove rounded-xl border border-rose-200 px-4 py-3 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-400/30 dark:hover:bg-rose-400/10" data-remove-rule>Remove</button>
                                </div>
                                <div class="commission-rule-row rounded-2xl border border-slate-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950" data-rule-row>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Minimum MTD %</span>
                                        <input type="number" name="rules[2][minimum_mtd_percent]" value="100" min="0" step="0.01" placeholder="Example: 100" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    </label>
                                    <label class="block min-w-0">
                                        <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Commission %</span>
                                        <input type="number" name="rules[2][commission_percent]" value="25" min="0" max="100" step="0.01" placeholder="Example: 25" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    </label>
                                    <button type="button" class="commission-rule-remove rounded-xl border border-rose-200 px-4 py-3 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-400/30 dark:hover:bg-rose-400/10" data-remove-rule>Remove</button>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit" class="rounded-xl bg-[var(--brand-primary)] px-5 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90">
                                Create Profile
                            </button>
                        </div>
                    </div>
                </form>

                <div class="space-y-4">
                    @forelse ($profiles as $profile)
                        <form method="POST" action="{{ route('admin.commission-profiles.update', $profile) }}" class="rounded-2xl border border-slate-200 p-5 dark:border-zinc-800" data-rule-form>
                            @csrf
                            @method('PUT')

                            <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                                <div>
                                    <input name="name" value="{{ old('name', $profile->name) }}" required class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm font-bold shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <textarea name="description" rows="2" class="mt-3 w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" placeholder="Description">{{ old('description', $profile->description) }}</textarea>
                                </div>
                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-zinc-300">
                                    <input type="checkbox" name="is_default" value="1" @checked($profile->is_default) class="rounded border-slate-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)]">
                                    Default
                                </label>
                            </div>

                            <div class="mt-4 space-y-2" data-rules>
                                @foreach ($profile->rules as $rule)
                                    <div class="commission-rule-row rounded-2xl border border-slate-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950" data-rule-row>
                                        <label class="block min-w-0">
                                            <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Minimum MTD %</span>
                                            <input type="number" name="rules[{{ $loop->index }}][minimum_mtd_percent]" value="{{ $rule->minimum_mtd_percent + 0 }}" min="0" step="0.01" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        </label>
                                        <label class="block min-w-0">
                                            <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Commission %</span>
                                            <input type="number" name="rules[{{ $loop->index }}][commission_percent]" value="{{ $rule->commission_percent + 0 }}" min="0" max="100" step="0.01" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        </label>
                                        <button type="button" class="commission-rule-remove rounded-xl border border-rose-200 px-4 py-3 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-400/30 dark:hover:bg-rose-400/10" data-remove-rule>Remove</button>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 flex justify-end gap-2">
                                <button type="button" class="rounded-xl border border-[var(--brand-primary)] px-4 py-2 text-sm font-bold text-[var(--brand-primary)] hover:bg-[var(--brand-soft)]" data-add-rule>
                                    + Add Rule
                                </button>
                                <button type="submit" class="rounded-xl bg-[var(--brand-primary)] px-5 py-2 text-sm font-bold text-white hover:opacity-90">
                                    Save Profile
                                </button>
                            </div>
                        </form>
                    @empty
                        <div class="rounded-2xl border border-slate-200 p-8 text-center text-sm text-slate-500 dark:border-zinc-800 dark:text-zinc-400">
                            No commission profiles yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const renumberRules = (form) => {
                form.querySelectorAll('[data-rule-row]').forEach((row, index) => {
                    row.querySelectorAll('input').forEach((input) => {
                        input.name = input.name.replace(/rules\[\d+\]/, `rules[${index}]`);
                    });
                });
            };

            document.querySelectorAll('[data-rule-form]').forEach((form) => {
                form.addEventListener('click', (event) => {
                    const addButton = event.target.closest('[data-add-rule]');
                    const removeButton = event.target.closest('[data-remove-rule]');

                    if (addButton) {
                        const rules = form.querySelector('[data-rules]');
                        const row = document.createElement('div');
                        row.className = 'commission-rule-row rounded-2xl border border-slate-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950';
                        row.setAttribute('data-rule-row', '');
                        row.innerHTML = `
                            <label class="block min-w-0">
                                <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Minimum MTD %</span>
                                <input type="number" name="rules[0][minimum_mtd_percent]" min="0" step="0.01" placeholder="Example: 75" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            </label>
                            <label class="block min-w-0">
                                <span class="mb-1 block text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Commission %</span>
                                <input type="number" name="rules[0][commission_percent]" min="0" max="100" step="0.01" placeholder="Example: 20" class="w-full rounded-xl border-slate-300 px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            </label>
                            <button type="button" class="commission-rule-remove rounded-xl border border-rose-200 px-4 py-3 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-400/30 dark:hover:bg-rose-400/10" data-remove-rule>Remove</button>
                        `;
                        rules.appendChild(row);
                        renumberRules(form);
                    }

                    if (removeButton && form.querySelectorAll('[data-rule-row]').length > 1) {
                        removeButton.closest('[data-rule-row]').remove();
                        renumberRules(form);
                    }
                });
            });

        });
    </script>
</x-app-layout>
