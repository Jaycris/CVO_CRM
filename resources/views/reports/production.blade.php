<x-app-layout>
    <x-slot name="header">
        Production Report
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Production Report</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Monitor project volume, fulfillment progress, and production staff workload.
                </p>
            </div>

            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="search"
                       name="search"
                       value="{{ $search }}"
                       placeholder="Search projects..."
                       class="h-11 w-72 rounded-xl border border-slate-300 bg-white px-4 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                <button type="submit"
                        class="h-11 rounded-xl bg-zinc-950 px-5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    Search
                </button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($summaryCards as $card)
                @php
                    $hintClass = match ($card['tone']) {
                        'rose' => 'text-rose-600 dark:text-rose-300',
                        'sky' => 'text-sky-600 dark:text-sky-300',
                        'amber' => 'text-amber-600 dark:text-amber-300',
                        default => 'text-emerald-600 dark:text-emerald-300',
                    };
                @endphp
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <p class="mt-5 text-3xl font-bold text-slate-950 dark:text-zinc-100">{{ $card['count'] }}</p>
                    <p class="mt-2 text-sm {{ $hintClass }}">{{ $card['hint'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Project Category Breakdown</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Publishing, Marketing, and Events project movement.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                            <tr>
                                <th class="px-6 py-4">Category</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Pending</th>
                                <th class="px-6 py-4">In Progress</th>
                                <th class="px-6 py-4">Fulfilled</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($trackerBreakdown as $row)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $row['label'] }}</td>
                                    <td class="px-6 py-4">{{ $row['total'] }}</td>
                                    <td class="px-6 py-4">{{ $row['pending'] }}</td>
                                    <td class="px-6 py-4">{{ $row['in_progress'] }}</td>
                                    <td class="px-6 py-4">{{ $row['fulfilled'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 dark:text-zinc-400">No production projects yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Fulfillment Officer Workload</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Assigned fulfillment ownership and average progress.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                        <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                            <tr>
                                <th class="px-6 py-4">Fulfillment Officer</th>
                                <th class="px-6 py-4">Total</th>
                                <th class="px-6 py-4">Pending</th>
                                <th class="px-6 py-4">In Progress</th>
                                <th class="px-6 py-4">Fulfilled</th>
                                <th class="px-6 py-4">Progress</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($fulfillmentWorkload as $row)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $row['name'] }}</td>
                                    <td class="px-6 py-4">{{ $row['total'] }}</td>
                                    <td class="px-6 py-4">{{ $row['pending'] }}</td>
                                    <td class="px-6 py-4">{{ $row['in_progress'] }}</td>
                                    <td class="px-6 py-4">{{ $row['fulfilled'] }}</td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="h-2 w-24 overflow-hidden rounded-full bg-slate-100 dark:bg-zinc-800">
                                                <div class="h-full rounded-full bg-emerald-500" style="width: {{ $row['average_progress'] }}%"></div>
                                            </div>
                                            <span class="text-xs font-semibold text-slate-600 dark:text-zinc-300">{{ $row['average_progress'] }}%</span>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-zinc-400">No fulfillment workload yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Production Staff Task Load</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Task counts by assigned production member.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-4">Production Staff</th>
                            <th class="px-6 py-4">Total Tasks</th>
                            <th class="px-6 py-4">Pending</th>
                            <th class="px-6 py-4">In Progress</th>
                            <th class="px-6 py-4">Done</th>
                            <th class="px-6 py-4">Average Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($staffTaskLoad as $row)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $row['name'] }}</td>
                                <td class="px-6 py-4">{{ $row['total'] }}</td>
                                <td class="px-6 py-4">{{ $row['pending'] }}</td>
                                <td class="px-6 py-4">{{ $row['in_progress'] }}</td>
                                <td class="px-6 py-4">{{ $row['done'] }}</td>
                                <td class="px-6 py-4">{{ $row['average_progress'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500 dark:text-zinc-400">No production tasks yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Recent Production Projects</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Latest endorsed projects included in this report.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-zinc-800">
                    <thead class="bg-slate-50 text-left text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-zinc-900/80 dark:text-zinc-400">
                        <tr>
                            <th class="px-6 py-4">Project ID</th>
                            <th class="px-6 py-4">Brand</th>
                            <th class="px-6 py-4">Author / Book Title</th>
                            <th class="px-6 py-4">Service</th>
                            <th class="px-6 py-4">Fulfillment Officer</th>
                            <th class="px-6 py-4">Agent</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Progress</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($recentProjects as $project)
                            @php
                                $endorsement = $project->endorsement;
                                $fulfillmentOfficer = trim(($project->fulfillmentOfficer?->first_name ?? '') . ' ' . ($project->fulfillmentOfficer?->last_name ?? '')) ?: '-';
                                $agent = trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: '-';
                                $statusLabel = str($project->status ?? 'pending')->replace('_', ' ')->title();
                            @endphp
                            <tr class="align-top">
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">PRJ-{{ str_pad($project->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $project->brand?->imprint_name ?? '-' }}</td>
                                <td class="max-w-sm px-6 py-4">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $endorsement?->author_name ?? '-' }}</p>
                                    <p class="mt-1 text-slate-500 dark:text-zinc-400">{{ $endorsement?->book_title ?? '-' }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $endorsement?->services ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $fulfillmentOfficer }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">{{ $agent }}</td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $statusLabel }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-zinc-100">{{ $project->progress_percentage }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-500 dark:text-zinc-400">No recent projects yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
