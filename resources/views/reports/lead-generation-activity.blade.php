<x-app-layout>
    <x-slot name="header">
        Lead Gen Activity
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Lead Gen Activity</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Monitor how many leads were mined and reviewed by Lead Generation today.
                </p>
            </div>

            <form method="GET" action="{{ route('reports.lead-generation-activity.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <input type="date"
                       name="date"
                       value="{{ $dateString }}"
                       class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                <button type="submit"
                        class="rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-800">
                    Apply
                </button>
                @if ($dateString !== now()->toDateString())
                    <a href="{{ route('reports.lead-generation-activity.index') }}"
                       class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Today
                    </a>
                @endif
            </form>
        </div>

        <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Mined Leads</p>
                <h2 class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($totalMinedToday) }}</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">{{ $date->format('F d, Y') }}</p>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-300">Reviewed Leads</p>
                <h2 class="mt-3 text-3xl font-bold text-slate-950 dark:text-white">{{ number_format($totalVerifiedToday) }}</h2>
                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">{{ $date->format('F d, Y') }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Lead Miners</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Leads mined on the selected date.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3">User</th>
                                <th class="px-5 py-3 text-right">Today</th>
                                <th class="px-5 py-3 text-right">Month</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($leadMiners as $miner)
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900 dark:text-zinc-100">
                                            {{ trim($miner->first_name . ' ' . $miner->last_name) ?: $miner->email }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ $miner->role?->name ?: $miner->department }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right text-lg font-bold text-slate-950 dark:text-white">
                                        {{ number_format((int) $miner->mined_today_count) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold text-slate-700 dark:text-zinc-300">
                                        {{ number_format((int) $miner->mined_month_count) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">
                                        No Lead Miner activity found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Verifiers</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Leads fully verified or touched with saved verification progress.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="px-5 py-3">User</th>
                                <th class="px-5 py-3 text-right">Today</th>
                                <th class="px-5 py-3 text-right">Month</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($verifiers as $verifier)
                                <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                    <td class="px-5 py-4">
                                        <p class="font-semibold text-slate-900 dark:text-zinc-100">
                                            {{ trim($verifier->first_name . ' ' . $verifier->last_name) ?: $verifier->email }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-slate-500 dark:text-zinc-400">{{ $verifier->role?->name ?: $verifier->department }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right text-lg font-bold text-slate-950 dark:text-white">
                                        {{ number_format((int) $verifier->verified_today_count) }}
                                    </td>
                                    <td class="px-5 py-4 text-right font-semibold text-slate-700 dark:text-zinc-300">
                                        {{ number_format((int) $verifier->verified_month_count) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">
                                        No Verifier activity found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
