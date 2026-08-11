<x-app-layout>
    <x-slot name="header">
        Sales Dashboard MTD
    </x-slot>

    @php
        $brandQuery = $brandId ? ['brand_id' => $brandId] : [];
        $money = fn ($value) => '$' . number_format((float) $value, 2);
        $summaryCards = [
            ['label' => 'Global MTD', 'value' => $summary['global']['mtd'], 'hint' => 'All credited sales this month', 'tone' => 'emerald'],
            ['label' => 'Remaining Target MTD', 'value' => $summary['global']['remaining'], 'hint' => 'Remaining against global target', 'tone' => 'rose'],
            ['label' => 'Remote MTD', 'value' => $summary['remote']['mtd'], 'hint' => 'Remote team credited sales', 'tone' => 'sky'],
            ['label' => 'Site MTD', 'value' => $summary['site']['mtd'], 'hint' => 'Site team credited sales', 'tone' => 'amber'],
        ];
        $toneClasses = [
            'emerald' => 'text-emerald-600 dark:text-emerald-300',
            'rose' => 'text-rose-600 dark:text-rose-300',
            'sky' => 'text-sky-600 dark:text-sky-300',
            'amber' => 'text-amber-600 dark:text-amber-300',
        ];
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Sales Dashboard MTD</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Track monthly credited sales, targets, and remaining target amounts.
                </p>
            </div>
        </div>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-medium text-emerald-700 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-800 dark:border-amber-400/20 dark:bg-amber-400/10 dark:text-amber-100">
            PHP totals, exchange-rate details, hold percentages, and net commissions are handled in CreatiVision HRIS.
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <h2 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $money($card['value']) }}</h2>
                    <p class="mt-2 text-sm {{ $toneClasses[$card['tone']] }}">{{ $card['hint'] }}</p>
                </section>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-5 xl:grid-cols-3">
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Global Target</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold text-slate-700 dark:text-zinc-200">MTD Progress</span>
                            <span class="font-bold text-emerald-600 dark:text-emerald-300">{{ number_format($summary['global']['percent'], 2) }}%</span>
                        </div>
                        <div class="mt-2 h-3 rounded-full bg-slate-100 dark:bg-zinc-800">
                            <div class="h-3 rounded-full bg-emerald-500" style="width: {{ min($summary['global']['percent'], 100) }}%;"></div>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-slate-500 dark:text-zinc-400">Target</dt>
                            <dd class="mt-1 font-bold text-slate-900 dark:text-zinc-100">{{ $money($summary['global']['target']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-zinc-400">Remaining</dt>
                            <dd class="mt-1 font-bold text-rose-600 dark:text-rose-300">{{ $money($summary['global']['remaining']) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Remote Target</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold text-slate-700 dark:text-zinc-200">MTD Progress</span>
                            <span class="font-bold text-sky-600 dark:text-sky-300">{{ number_format($summary['remote']['percent'], 2) }}%</span>
                        </div>
                        <div class="mt-2 h-3 rounded-full bg-slate-100 dark:bg-zinc-800">
                            <div class="h-3 rounded-full bg-sky-500" style="width: {{ min($summary['remote']['percent'], 100) }}%;"></div>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-slate-500 dark:text-zinc-400">Target</dt>
                            <dd class="mt-1 font-bold text-slate-900 dark:text-zinc-100">{{ $money($summary['remote']['target']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-zinc-400">Remaining</dt>
                            <dd class="mt-1 font-bold text-rose-600 dark:text-rose-300">{{ $money($summary['remote']['remaining']) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>

            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Site Target</h2>
                <div class="mt-5 space-y-4">
                    <div>
                        <div class="flex justify-between text-sm">
                            <span class="font-semibold text-slate-700 dark:text-zinc-200">MTD Progress</span>
                            <span class="font-bold text-amber-600 dark:text-amber-300">{{ number_format($summary['site']['percent'], 2) }}%</span>
                        </div>
                        <div class="mt-2 h-3 rounded-full bg-slate-100 dark:bg-zinc-800">
                            <div class="h-3 rounded-full bg-amber-500" style="width: {{ min($summary['site']['percent'], 100) }}%;"></div>
                        </div>
                    </div>
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt class="text-slate-500 dark:text-zinc-400">Target</dt>
                            <dd class="mt-1 font-bold text-slate-900 dark:text-zinc-100">{{ $money($summary['site']['target']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-500 dark:text-zinc-400">Remaining</dt>
                            <dd class="mt-1 font-bold text-rose-600 dark:text-rose-300">{{ $money($summary['site']['remaining']) }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </div>

        <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Agent MTD Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Agent sales totals, service/markup split, commission, and target progress for {{ $month->format('F Y') }}.</p>
                    </div>
                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <input type="text" name="month" value="{{ $month->format('Y-m') }}" placeholder="YYYY-MM"
                               class="h-11 w-32 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @if ($brands->isNotEmpty())
                            <select name="brand_id" class="h-11 w-56 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">All brands</option>
                                @foreach ($brands as $brand)
                                    <option value="{{ $brand->id }}" @selected($brandId === $brand->id)>{{ $brand->imprint_name }}</option>
                                @endforeach
                            </select>
                        @endif
                        <input type="search" name="search" value="{{ $search }}" placeholder="Search agent..."
                               class="h-11 w-64 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="submit" class="h-11 rounded-xl bg-[var(--brand-primary)] px-5 text-sm font-semibold text-white shadow-sm hover:opacity-90">
                            Search
                        </button>
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1280px] divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-4">Agent</th>
                            <th class="px-6 py-4">Work Type</th>
                            <th class="px-6 py-4">MTD</th>
                            <th class="px-6 py-4">Service MTD</th>
                            <th class="px-6 py-4">Markup MTD</th>
                            <th class="px-6 py-4">Target</th>
                            <th class="px-6 py-4">MTD %</th>
                            <th class="px-6 py-4">Service Comm</th>
                            <th class="px-6 py-4">Markup Comm</th>
                            <th class="px-6 py-4">USD Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($agentRows as $row)
                            <tr class="align-top hover:bg-slate-50/70 dark:hover:bg-zinc-800/60">
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                    {{ trim(($row['agent']->first_name ?? '') . ' ' . ($row['agent']->last_name ?? '')) ?: 'Unknown Agent' }}
                                    <p class="mt-1 text-xs font-medium text-slate-500 dark:text-zinc-400">{{ $row['agent']->brand?->imprint_name ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ match($row['work_type']) {
                                            'remote' => 'Remote',
                                            'site' => 'On-site',
                                            'part_time' => 'Part-time',
                                            default => 'Not set',
                                        } }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-bold text-slate-900 dark:text-zinc-100">{{ $money($row['mtd']) }}</td>
                                <td class="px-6 py-4 font-semibold text-slate-700 dark:text-zinc-200">
                                    {{ $money($row['service_mtd']) }}
                                    <p class="mt-1 text-xs font-medium text-slate-500 dark:text-zinc-400">
                                        Commissionable {{ $money($row['commissionable_service_mtd'] ?? 0) }}
                                    </p>
                                    @if (($row['threshold_applied_amount'] ?? 0) > 0)
                                        <p class="mt-1 text-xs font-semibold text-amber-600 dark:text-amber-300">
                                            {{ $money($row['threshold_applied_amount']) }} threshold
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-700 dark:text-zinc-200">{{ $money($row['markup_mtd']) }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $money($row['target']) }}</td>
                                <td class="px-6 py-4">
                                    <div class="w-36">
                                        <div class="flex justify-between text-xs font-semibold text-slate-500 dark:text-zinc-400">
                                            <span>{{ number_format($row['percent'], 2) }}%</span>
                                        </div>
                                        <div class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-zinc-800">
                                            <div class="h-2 rounded-full bg-[var(--brand-primary)]" style="width: {{ min($row['percent'], 100) }}%;"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-900 dark:text-zinc-100">{{ $money($row['service_commission']) }}</span>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ number_format($row['service_commission_percent'], 2) }}%</p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="font-bold text-slate-900 dark:text-zinc-100">{{ $money($row['markup_commission']) }}</span>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">{{ number_format($row['markup_commission_percent'], 2) }}%</p>
                                </td>
                                <td class="px-6 py-4 font-bold text-emerald-600 dark:text-emerald-300">{{ $money($row['usd_total']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-16 text-center text-slate-500 dark:text-zinc-400">No sales agents found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($agentRows->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $agentRows->links() }}
                </div>
            @endif
        </section>

        @if ($canManageTargets)
            <section class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Manage Monthly Targets</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Set monthly targets, markup commission, and threshold exemptions. The monthly threshold deducts from Service MTD first, then Markup MTD when needed. Service commission tiers are managed in Admin > Commission Profiles.
                </p>

                <form method="POST" action="{{ route('reports.sales-performance.targets') }}" class="mt-6 space-y-6">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="month" value="{{ $month->format('Y-m') }}">
                    @if ($brandId)
                        <input type="hidden" name="brand_id" value="{{ $brandId }}">
                    @endif

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Global Target</span>
                            <input type="number" step="0.01" min="0" name="global_target" value="{{ $summary['global']['target'] }}"
                                   class="mt-2 h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Remote Target</span>
                            <input type="number" step="0.01" min="0" name="remote_target" value="{{ $summary['remote']['target'] }}"
                                   class="mt-2 h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </label>
                        <label class="block">
                            <span class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Site Target</span>
                            <input type="number" step="0.01" min="0" name="site_target" value="{{ $summary['site']['target'] }}"
                                   class="mt-2 h-12 w-full rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        </label>
                    </div>

                    <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                        <table class="min-w-[1160px] divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                            <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                                <tr>
                                    <th class="px-5 py-3">Agent</th>
                                    <th class="px-5 py-3">Work Type</th>
                                    <th class="px-5 py-3">Agent Target</th>
                                    <th class="px-5 py-3">Service Profile</th>
                                    <th class="px-5 py-3">Markup %</th>
                                    <th class="px-5 py-3">Threshold</th>
                                    <th class="px-5 py-3">Exempt</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                                @foreach ($agentRows as $row)
                                    <tr>
                                        <td class="px-5 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                            {{ trim(($row['agent']->first_name ?? '') . ' ' . ($row['agent']->last_name ?? '')) }}
                                            <input type="hidden" name="agents[{{ $row['id'] }}][id]" value="{{ $row['id'] }}">
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                                {{ match($row['work_type']) {
                                                    'remote' => 'Remote',
                                                    'site' => 'On-site',
                                                    'part_time' => 'Part-time',
                                                    default => 'Not set',
                                                } }}
                                            </span>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-500">Set in User Record</p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <input type="number" step="0.01" min="0" name="agents[{{ $row['id'] }}][target]" value="{{ $row['target'] }}"
                                                   class="h-11 w-44 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        </td>
                                        <td class="px-5 py-4">
                                            <span class="font-semibold text-slate-900 dark:text-zinc-100">{{ $row['commission_profile_name'] ?? 'Default Service Tiers' }}</span>
                                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Managed in Commission Profiles</p>
                                        </td>
                                        <td class="px-5 py-4">
                                            <input type="number" step="0.01" min="0" max="100" name="agents[{{ $row['id'] }}][markup_commission_percent]" value="{{ $row['markup_commission_percent'] }}"
                                                   class="h-11 w-32 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        </td>
                                        <td class="px-5 py-4">
                                            <input type="number" step="0.01" min="0" name="agents[{{ $row['id'] }}][commission_threshold_amount]" value="{{ $row['commission_threshold_amount'] }}"
                                                   class="h-11 w-36 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                        </td>
                                        <td class="px-5 py-4">
                                            <input type="hidden" name="agents[{{ $row['id'] }}][is_commission_threshold_exempt]" value="0">
                                            <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-zinc-200">
                                                <input type="checkbox" name="agents[{{ $row['id'] }}][is_commission_threshold_exempt]" value="1" @checked($row['is_commission_threshold_exempt'])
                                                       class="rounded border-slate-300 text-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950">
                                                Exempt
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" class="rounded-xl bg-[var(--brand-primary)] px-6 py-3 text-sm font-bold text-white shadow-sm hover:opacity-90">
                            Save Targets
                        </button>
                    </div>
                </form>
            </section>
        @endif
    </div>
</x-app-layout>
