<x-app-layout>
    <x-slot name="header">
        Production
    </x-slot>

    @php
        $trackerLabels = [
            'publishing' => 'Publishing',
            'marketing' => 'Marketing',
            'events' => 'Events',
        ];
        $statusLabels = [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'fulfilled' => 'Done',
            'hold_off' => 'Hold Off',
        ];
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Task Tracker</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                View all assigned production tasks and service inclusions.
            </p>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="grid gap-4 xl:grid-cols-[minmax(220px,1fr)_auto] xl:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Production Task Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Each service inclusion appears as its own task row.</p>
                    </div>

                    <form method="GET" action="{{ route('production.tasks.tracker') }}" class="flex flex-wrap items-center gap-2 xl:justify-end">
                        @if (count($allowedTrackers) > 1)
                            <select name="tracker"
                                    class="h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="all" @selected($tracker === 'all')>All categories</option>
                                @foreach ($allowedTrackers as $trackerKey)
                                    <option value="{{ $trackerKey }}" @selected($tracker === $trackerKey)>{{ $trackerLabels[$trackerKey] ?? str($trackerKey)->title() }}</option>
                                @endforeach
                            </select>
                        @endif

                        <select name="status"
                                class="h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="all" @selected($status === 'all')>All status</option>
                            @foreach ($statusLabels as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>

                        <input type="text"
                               name="search"
                               value="{{ $search }}"
                               placeholder="Search tasks..."
                               class="h-11 w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">

                        <button type="submit"
                                class="h-11 rounded-xl bg-zinc-950 px-4 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Search
                        </button>

                        @if ($search !== '' || $status !== 'all' || $tracker !== 'all')
                            <a href="{{ route('production.tasks.tracker') }}"
                               class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-[1320px] w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="w-[10%] px-3 py-4">Task ID</th>
                            <th class="w-[10%] px-3 py-4">Project ID</th>
                            <th class="w-[11%] px-3 py-4">Service Category</th>
                            <th class="w-[22%] px-3 py-4">Author / Book Title</th>
                            <th class="w-[13%] px-3 py-4">Task Label</th>
                            <th class="w-[13%] px-3 py-4">Assigned To</th>
                            <th class="w-[10%] px-3 py-4">Status</th>
                            <th class="w-[11%] px-3 py-4">Result Link</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($tasks as $task)
                            @php
                                $project = $task->project;
                                $endorsement = $project?->endorsement;
                                $assigneeName = trim(($task->assignedUser?->first_name ?? '') . ' ' . ($task->assignedUser?->last_name ?? '')) ?: 'Unassigned';
                            @endphp
                            <tr class="align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60">
                                <td class="px-3 py-4 font-semibold text-slate-900 dark:text-zinc-100">TASK-{{ str_pad((string) $task->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-4 font-semibold text-slate-900 dark:text-zinc-100">PRJ-{{ str_pad((string) $project?->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $trackerLabels[$project?->tracker_type] ?? str($project?->tracker_type)->title() }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $endorsement?->author_name ?: '-' }}</p>
                                    <p class="mt-1 line-clamp-2" title="{{ $endorsement?->book_title }}">{{ $endorsement?->book_title ?: '-' }}</p>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $task->title }}</p>
                                </td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">{{ $assigneeName }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $statusLabels[$task->status] ?? str($task->status)->replace('_', ' ')->title() }}
                                    </span>
                                    <p class="mt-2 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ $task->progress }}%</p>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug">
                                    @if ($task->result_link)
                                        <a href="{{ $task->result_link }}" target="_blank" rel="noopener" class="font-semibold text-[var(--brand-primary)] underline dark:text-[var(--brand-accent)]">Open Result</a>
                                    @else
                                        <span class="text-slate-500 dark:text-zinc-400">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No production tasks yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tasks->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $tasks->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
