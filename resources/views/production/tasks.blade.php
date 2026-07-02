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
        $isCompletedTaskPage = ($taskMode ?? 'new') === 'completed';
        $taskListRoute = $isCompletedTaskPage ? route('production.tasks.completed') : route('production.tasks.index');
    @endphp

    <div class="space-y-6"
         x-data="{
            selected: [],
            selectAll: false,
            statusOpen: false,
            notesOpen: false,
            nextStatus: 'pending',
            taskProgress: 50,
            taskNotes: '',
            resultLink: '',
            attachResultLink: false,
            toggleAll(ids) {
                this.selectAll = ! this.selectAll;
                this.selected = this.selectAll ? ids : [];
            },
            toggleSelection(id, ids) {
                const value = String(id);
                this.selected = this.selected.includes(value)
                    ? this.selected.filter((selectedId) => selectedId !== value)
                    : [...this.selected, value];
                this.syncSelectAll(ids);
            },
            syncSelectAll(ids) {
                this.selectAll = ids.length > 0 && ids.every((id) => this.selected.includes(id));
            },
            openStatus() {
                if (this.selected.length === 0) return;
                this.nextStatus = 'pending';
                this.taskProgress = 50;
                this.resultLink = '';
                this.attachResultLink = false;
                this.statusOpen = true;
            },
            openNotes() {
                if (this.selected.length === 0) return;
                this.taskNotes = '';
                this.notesOpen = true;
            },
         }">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $isCompletedTaskPage ? 'My Complete Tasks' : 'My New Task' }}</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                {{ $isCompletedTaskPage ? 'View production work you already marked done.' : 'View active production work assigned to you by Fulfillment.' }}
            </p>
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

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Total Tasks</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['total'] }}</h3>
                <p class="mt-2 text-sm text-amber-600 dark:text-amber-300">Assigned to you</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Pending</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['pending'] }}</h3>
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">Not started</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">In Progress</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['in_progress'] }}</h3>
                <p class="mt-2 text-sm text-sky-600 dark:text-sky-300">Being worked</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Fulfilled</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['fulfilled'] }}</h3>
                <p class="mt-2 text-sm text-emerald-600 dark:text-emerald-300">Completed</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Hold Off</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['hold_off'] }}</h3>
                <p class="mt-2 text-sm text-orange-600 dark:text-orange-300">Paused</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="grid gap-4 xl:grid-cols-[minmax(220px,1fr)_auto] xl:items-center">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Task Directory</h2>
                        <p x-show="selected.length > 0" x-cloak class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                            <span x-text="selected.length"></span> selected
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center justify-start gap-2 xl:justify-end">
                        @if ($canUpdateProjects)
                            <button type="button"
                                    x-on:click="openStatus()"
                                    x-bind:disabled="selected.length === 0"
                                    x-bind:class="selected.length === 0 ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200'"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border shadow-sm"
                                    title="Update selected task status">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 12 2 2 4-4" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>

                            <button type="button"
                                    x-on:click="openNotes()"
                                    x-bind:disabled="selected.length === 0"
                                    x-bind:class="selected.length === 0 ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500' : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200'"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border shadow-sm"
                                    title="Add task note">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487 18.55 2.8a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                </svg>
                            </button>
                        @endif

                        <form method="GET" action="{{ $taskListRoute }}" class="flex flex-wrap items-center gap-2">
                            @if (! $isCompletedTaskPage)
                                <select name="status"
                                        class="h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <option value="all" @selected($status === 'all')>All status</option>
                                    @foreach ($statusLabels as $statusKey => $statusLabel)
                                        @continue($statusKey === 'fulfilled')
                                        <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                                    @endforeach
                                </select>
                            @endif
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search tasks..."
                                   class="h-11 w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">

                            <button type="submit"
                                    class="h-11 rounded-xl bg-zinc-950 px-4 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>

                            @if ($search !== '' || (! $isCompletedTaskPage && $status !== 'all'))
                                <a href="{{ $taskListRoute }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @php $visibleTaskIds = $tasks->pluck('id')->map(fn ($id) => (string) $id)->values(); @endphp
                <table class="min-w-[1180px] w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="w-[4%] px-3 py-4">
                                <input type="checkbox"
                                       class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                       x-bind:checked="selectAll"
                                       x-on:change="toggleAll(@js($visibleTaskIds))">
                            </th>
                            <th class="w-[10%] px-3 py-4">Task ID</th>
                            <th class="w-[12%] px-3 py-4">Service Category</th>
                            <th class="w-[25%] px-3 py-4">Author / Book Title</th>
                            <th class="w-[13%] px-3 py-4">Service</th>
                            <th class="w-[11%] px-3 py-4">Status</th>
                            <th class="w-[10%] px-3 py-4">Agent</th>
                            <th class="w-[11%] px-3 py-4">Result Link</th>
                            <th class="w-[14%] px-3 py-4">Instruction</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($tasks as $task)
                            @php
                                $project = $task->project;
                                $endorsement = $project?->endorsement;
                                $agentName = trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: 'Unknown';
                            @endphp
                            <tr class="cursor-pointer align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60"
                                x-on:click="if ($event.target.closest('button,a,input,select,textarea,label')) return; toggleSelection('{{ $task->id }}', @js($visibleTaskIds))"
                                x-bind:class="selected.includes('{{ $task->id }}') ? 'bg-amber-50/70 dark:bg-amber-400/10' : ''">
                                <td class="px-3 py-4">
                                    <input type="checkbox"
                                           value="{{ $task->id }}"
                                           x-model="selected"
                                           x-on:change="syncSelectAll(@js($visibleTaskIds))"
                                           class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                </td>
                                <td class="px-3 py-4 font-semibold text-slate-900 dark:text-zinc-100">TASK-{{ str_pad((string) $task->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $trackerLabels[$project?->tracker_type] ?? str($project?->tracker_type)->title() }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $endorsement?->author_name ?: '-' }}</p>
                                    <p class="mt-1 line-clamp-2" title="{{ $endorsement?->book_title }}">{{ $endorsement?->book_title ?: '-' }}</p>
                                    <p class="mt-1 text-[11px] font-semibold text-sky-600 dark:text-sky-300">{{ $task->title }}</p>
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <p>{{ $endorsement?->services ?: '-' }}</p>
                                    @if ($task->items->isNotEmpty())
                                        <p class="mt-1 text-[11px] text-slate-500 dark:text-zinc-400">{{ $task->items->pluck('name')->join(', ') }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-4">
                                    <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600 dark:bg-zinc-800 dark:text-zinc-300">
                                        {{ $statusLabels[$task->status] ?? str($task->status)->replace('_', ' ')->title() }}
                                    </span>
                                    <p class="mt-2 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">{{ $task->progress }}%</p>
                                    @if ($task->notes)
                                        <p class="mt-2 line-clamp-2 text-[11px] leading-snug text-slate-500 dark:text-zinc-400" title="{{ $task->notes }}">{{ $task->notes }}</p>
                                    @endif
                                </td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">{{ $agentName }}</td>
                                <td class="break-words px-3 py-4 leading-snug">
                                    @if ($task->result_link)
                                        <a href="{{ $task->result_link }}" target="_blank" rel="noopener" class="font-semibold text-[var(--brand-primary)] underline dark:text-[var(--brand-accent)]">Open Result</a>
                                    @else
                                        <span class="text-slate-500 dark:text-zinc-400">-</span>
                                    @endif
                                </td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $task->instructions ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    {{ $isCompletedTaskPage ? 'No completed production tasks yet.' : 'No new production tasks assigned to you yet.' }}
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

        @if ($canUpdateProjects)
            <div x-show="statusOpen" x-cloak class="crm-modal-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6">
                <form method="POST" action="{{ route('production.tasks.bulk-update') }}" class="crm-modal-panel w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                    <template x-for="taskId in selected" :key="`status-${taskId}`">
                        <input type="hidden" name="task_ids[]" x-bind:value="taskId">
                    </template>

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Update Task Status</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                Change status for <span x-text="selected.length"></span> selected task(s).
                            </p>
                        </div>
                        <button type="button" x-on:click="statusOpen = false" class="text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5">
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Status</label>
                        <select name="status"
                                x-model="nextStatus"
                                x-on:change="if (nextStatus !== 'fulfilled') { attachResultLink = false; resultLink = ''; }"
                                required
                                class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            @foreach ($statusLabels as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}">{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4" x-show="nextStatus === 'in_progress'" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Progress</label>
                            <span class="text-sm font-bold text-sky-600 dark:text-sky-300" x-text="`${taskProgress}%`"></span>
                        </div>
                        <input type="range" name="progress" min="1" max="99" x-model="taskProgress"
                               x-bind:disabled="nextStatus !== 'in_progress'"
                               class="mt-3 w-full accent-sky-600">
                    </div>

                    <label class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm dark:border-zinc-800 dark:bg-zinc-950"
                           x-show="nextStatus === 'fulfilled'"
                           x-cloak>
                        <input type="checkbox"
                               x-model="attachResultLink"
                               x-on:change="if (! attachResultLink) resultLink = ''"
                               class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            <span class="font-semibold text-slate-900 dark:text-zinc-100">Add result link</span>
                            <span class="mt-1 block text-xs text-slate-500 dark:text-zinc-400">Use this only when the completed task has a file, proof, or Google Drive link.</span>
                        </span>
                    </label>

                    <div class="mt-4" x-show="nextStatus === 'fulfilled' && attachResultLink" x-cloak>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Result Link</label>
                        <input type="url"
                               name="result_link"
                               x-model="resultLink"
                               x-bind:disabled="nextStatus !== 'fulfilled' || ! attachResultLink"
                               placeholder="Paste Google Drive, proof, or result link"
                               class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" x-on:click="statusOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Save Status
                        </button>
                    </div>
                </form>
            </div>

            <div x-show="notesOpen" x-cloak class="crm-modal-backdrop fixed inset-0 z-50 flex items-center justify-center overflow-y-auto bg-black/40 px-4 py-6">
                <form method="POST" action="{{ route('production.tasks.bulk-update') }}" class="crm-modal-panel w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                    <template x-for="taskId in selected" :key="`notes-${taskId}`">
                        <input type="hidden" name="task_ids[]" x-bind:value="taskId">
                    </template>

                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Task Notes</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                Save notes for <span x-text="selected.length"></span> selected task(s).
                            </p>
                        </div>
                        <button type="button" x-on:click="notesOpen = false" class="text-slate-500 hover:text-slate-800 dark:text-zinc-400 dark:hover:text-zinc-100">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5">
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Notes</label>
                        <textarea name="notes"
                                  x-model="taskNotes"
                                  rows="5"
                                  class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"
                                  placeholder="Add task notes"></textarea>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" x-on:click="notesOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Save Notes
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
