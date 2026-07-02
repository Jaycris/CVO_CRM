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
            'fulfilled' => 'Completed',
            'hold_off' => 'Hold Off',
        ];
        $welcomeLabels = [
            'pending' => 'Pending',
            'done' => 'Done',
            'other_reason' => 'Other Reason',
        ];
        $isSalesDepartmentView = auth()->user()?->department === 'Sales'
            && auth()->user()?->role?->name !== 'Admin';
        $projectTaskOptions = $projects->getCollection()->mapWithKeys(function ($project) {
            $assignedItemIds = $project->tasks->flatMap(fn ($task) => $task->items->pluck('service_item_id'))->filter()->values();

            return [(string) $project->id => [
                'projectId' => $project->id,
                'serviceName' => $project->endorsement?->services ?: 'Production Work',
                'items' => ($project->endorsement?->service?->inclusions ?? collect())->map(fn ($item) => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'assigned' => $assignedItemIds->contains($item->id),
                ])->values()->all(),
            ]];
        });
    @endphp

    <div class="space-y-6"
         x-data="{
            selected: [],
            selectAll: false,
            detailOpen: false,
            detail: {},
            reasonOpen: false,
            reasonText: '',
            welcomeOpen: false,
            welcomeStatus: 'pending',
            welcomeReason: '',
            assignOpen: false,
            activeProject: { projectId: '', serviceName: '', items: [] },
            taskTitle: '',
            selectedTaskItems: [],
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
            openDetail(project) {
                this.detail = project;
                this.detailOpen = true;
            },
            openReason(reason = '') {
                this.reasonText = reason || 'No reason added.';
                this.reasonOpen = true;
            },
            openWelcome() {
                if (this.selected.length === 0) return;
                this.welcomeStatus = 'pending';
                this.welcomeReason = '';
                this.welcomeOpen = true;
            },
            openAssign() {
                if (this.selected.length !== 1) return;
                const projects = @js($projectTaskOptions);
                this.activeProject = projects[this.selected[0]] || { projectId: '', serviceName: '', items: [] };
                this.taskTitle = this.activeProject.serviceName;
                this.selectedTaskItems = [];
                this.assignOpen = true;
            },
         }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Fulfillment Tracker</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Track projects endorsed by Finance and assign them to the Production team.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                @if (count($allowedTrackers) > 1)
                    <a href="{{ route('production.projects.index', ['tracker' => 'all', 'status' => $status, 'search' => $search]) }}"
                       class="{{ $tracker === 'all' ? 'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' : 'bg-white text-slate-600 dark:bg-zinc-900 dark:text-zinc-300' }} rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold shadow-sm dark:border-zinc-800">
                        All
                    </a>
                @endif

                @foreach ($allowedTrackers as $trackerKey)
                    <a href="{{ route('production.projects.index', ['tracker' => $trackerKey, 'status' => $status, 'search' => $search]) }}"
                       class="{{ $tracker === $trackerKey ? 'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' : 'bg-white text-slate-600 dark:bg-zinc-900 dark:text-zinc-300' }} rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold shadow-sm dark:border-zinc-800">
                        {{ $trackerLabels[$trackerKey] ?? str($trackerKey)->title() }}
                    </a>
                @endforeach
            </div>
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
                <p class="text-sm text-slate-500 dark:text-zinc-400">Total Projects</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['total'] }}</h3>
                <p class="mt-2 text-sm text-amber-600 dark:text-amber-300">Endorsed to Production</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Pending</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['pending'] }}</h3>
                <p class="mt-2 text-sm text-rose-600 dark:text-rose-300">Waiting fulfillment</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">In Progress</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['in_progress'] }}</h3>
                <p class="mt-2 text-sm text-sky-600 dark:text-sky-300">Being worked</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Fulfilled</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['fulfilled'] }}</h3>
                <p class="mt-2 text-sm text-emerald-600 dark:text-emerald-300">Finished projects</p>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <p class="text-sm text-slate-500 dark:text-zinc-400">Hold Off</p>
                <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">{{ $summary['hold_off'] }}</h3>
                <p class="mt-2 text-sm text-orange-600 dark:text-orange-300">Paused work</p>
            </div>
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="grid gap-4 xl:grid-cols-[minmax(260px,1fr)_auto] xl:items-center">
                    <div class="min-w-0">
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Production Fulfillment Directory</h2>
                        @if ($canSelectProjects)
                            <p x-show="selected.length > 0" x-cloak class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                                <span x-text="selected.length"></span> selected
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-start gap-2 xl:justify-end">
                        @if ($canSelectProjects && $canUpdateProjects)
                            <button type="button"
                                    x-on:click="openWelcome()"
                                    x-bind:disabled="selected.length === 0"
                                    x-bind:class="selected.length === 0 ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500' : 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200'"
                                    class="h-11 shrink-0 rounded-xl border px-4 text-sm font-semibold shadow-sm">
                                Welcome Email
                            </button>
                        @endif

                        @if ($canSelectProjects && $canAssignProjects)
                            <button type="button"
                                    x-on:click="openAssign()"
                                    x-bind:disabled="selected.length !== 1"
                                    x-bind:class="selected.length !== 1 ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500' : 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200'"
                                    class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border shadow-sm"
                                    title="Create a task for one selected project">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15.75a4.5 4.5 0 1 0-9 0v.75A2.25 2.25 0 0 0 5.25 18.75h4.5A2.25 2.25 0 0 0 12 16.5v-.75Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 7.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        @endif

                        @if ($canSelectProjects && $canDeleteProjects)
                            <form method="POST"
                                  action="{{ route('production.projects.bulk-destroy') }}"
                                  x-on:submit="if (selected.length === 0 || !confirm('Delete selected fulfillment record(s)?')) { $event.preventDefault(); }">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                <template x-for="projectId in selected" :key="`delete-${projectId}`">
                                    <input type="hidden" name="project_ids[]" x-bind:value="projectId">
                                </template>
                                <button type="submit"
                                        x-bind:disabled="selected.length === 0"
                                        x-bind:class="selected.length === 0 ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500' : 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200'"
                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border shadow-sm"
                                        title="Delete selected fulfillment records">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                    </svg>
                                </button>
                            </form>
                        @endif

                        <form method="GET" action="{{ route('production.projects.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="tracker" value="{{ $tracker }}">
                            <select name="status"
                                    class="h-11 w-40 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="all" @selected($status === 'all')>All status</option>
                                @foreach ($statusLabels as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search projects..."
                                   class="h-11 w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500 2xl:w-80">

                            <button type="submit"
                                    class="h-11 shrink-0 rounded-xl bg-zinc-950 px-4 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>

                            @if ($search !== '' || $status !== 'all')
                                <a href="{{ route('production.projects.index', ['tracker' => $tracker]) }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @php $visibleProjectIds = $projects->pluck('id')->map(fn ($id) => (string) $id)->values(); @endphp
                <table class="min-w-[1180px] w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            @if ($canSelectProjects)
                                <th class="w-[4%] px-3 py-4">
                                    <input type="checkbox"
                                           class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                           x-bind:checked="selectAll"
                                           x-on:change="toggleAll(@js($visibleProjectIds))">
                                </th>
                            @endif
                            <th class="w-[5%] px-3 py-4">View</th>
                            <th class="w-[10%] px-3 py-4">Project ID</th>
                            <th class="w-[10%] px-3 py-4">Brand</th>
                            <th class="w-[8%] px-3 py-4">Date Sold</th>
                            <th class="w-[21%] px-3 py-4">Author / Book Title</th>
                            @unless ($isSalesDepartmentView)
                                <th class="w-[8%] px-3 py-4">Agreement</th>
                            @endunless
                            <th class="w-[10%] px-3 py-4">Service</th>
                            <th class="w-[10%] px-3 py-4">Welcome Email</th>
                            <th class="w-[11%] px-3 py-4">Fulfillment Officer</th>
                            <th class="w-[12%] px-3 py-4">Project Status</th>
                            @if ($showAgentColumn)
                                <th class="w-[12%] px-3 py-4">Agent</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($projects as $project)
                            @php
                                $endorsement = $project->endorsement;
                                $brand = $project->brand ?? $endorsement?->brand;
                                $brandName = $brand?->imprint_name ?? 'CreatiVision';
                                $brandPrimary = $brand?->primary_color ?: '#065f46';
                                $brandAccent = $brand?->accent_color ?: '#d1fae5';
                                $agentName = trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: 'Unknown';
                                $fulfillmentName = trim(($project->fulfillmentOfficer?->first_name ?? '') . ' ' . ($project->fulfillmentOfficer?->last_name ?? '')) ?: 'Unassigned';
                                $address = trim(collect([$endorsement?->street_name, $endorsement?->city_state, $endorsement?->zip_code])->filter()->implode(', '));
                                $progress = $project->progress_percentage;
                            @endphp
                            <tr class="{{ $canSelectProjects ? 'cursor-pointer' : '' }} align-top hover:bg-slate-50 dark:hover:bg-zinc-800/60"
                                @if ($canSelectProjects)
                                    x-on:click="if ($event.target.closest('button,a,input,select,textarea,label')) return; toggleSelection('{{ $project->id }}', @js($visibleProjectIds))"
                                    x-bind:class="selected.includes('{{ $project->id }}') ? 'bg-amber-50/70 dark:bg-amber-400/10' : ''"
                                @endif>
                                @if ($canSelectProjects)
                                    <td class="px-3 py-4">
                                        <input type="checkbox"
                                               value="{{ $project->id }}"
                                               x-model="selected"
                                               x-on:change="syncSelectAll(@js($visibleProjectIds))"
                                               class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                    </td>
                                @endif
                                <td class="px-3 py-4">
                                    <button type="button"
                                            x-on:click="openDetail(@js([
                                                'projectId' => 'PRJ-' . str_pad((string) $project->id, 5, '0', STR_PAD_LEFT),
                                                'brand' => $brandName,
                                                'tracker' => $trackerLabels[$project->tracker_type] ?? str($project->tracker_type)->title(),
                                                'dateSold' => $endorsement?->paymentRecord?->sold_date?->format('M d, Y') ?: '-',
                                                'author' => $endorsement?->author_name ?: '-',
                                                'bookTitle' => $endorsement?->book_title ?: '-',
                                                'agreement' => $endorsement?->contract_status === 'signed' ? 'Signed' : 'Pending',
                                                'service' => $endorsement?->services ?: '-',
                                                'welcomeEmail' => $welcomeLabels[$project->welcome_email_status ?? 'pending'] ?? 'Pending',
                                                'welcomeReason' => $project->welcome_email_reason ?: '-',
                                                'fulfillmentOfficer' => $fulfillmentName,
                                                'assignedTo' => $isSalesDepartmentView
                                                    ? '-'
                                                    : ($project->tasks->map(fn ($task) => trim(($task->assignedUser?->first_name ?? '') . ' ' . ($task->assignedUser?->last_name ?? '')))->filter()->unique()->implode(', ') ?: 'Unassigned'),
                                                'projectStatus' => $statusLabels[$project->status] ?? str($project->status)->replace('_', ' ')->title(),
                                                'progress' => $progress . '%',
                                                'contact' => $endorsement?->contact_number ?: '-',
                                                'email' => $endorsement?->email ?: '-',
                                                'address' => $address ?: '-',
                                                'endorsementNotes' => $project->endorsement_notes ?: '-',
                                                'assignmentInstruction' => $project->assignment_instruction ?: '-',
                                                'productionNotes' => $project->notes ?: '-',
                                                'agent' => $agentName,
                                            ]))"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-800"
                                            title="View project details">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12s-3.75 6.75-9.75 6.75S2.25 12 2.25 12Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                    </button>
                                </td>
                                <td class="px-3 py-4 font-semibold text-slate-900 dark:text-zinc-100">
                                    PRJ-{{ str_pad((string) $project->id, 5, '0', STR_PAD_LEFT) }}
                                    <div class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold uppercase text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                        {{ $trackerLabels[$project->tracker_type] ?? str($project->tracker_type)->title() }}
                                    </div>
                                </td>
                                <td class="px-3 py-4">
                                    <span class="inline-flex max-w-[8rem] items-center rounded-full px-2.5 py-1 text-[11px] font-semibold leading-tight"
                                          style="background-color: {{ $brandAccent }}; color: {{ $brandPrimary }};"
                                          title="{{ $brandName }}">
                                        {{ \Illuminate\Support\Str::limit($brandName, 18) }}
                                    </span>
                                </td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    {{ $endorsement?->paymentRecord?->sold_date?->format('M d, Y') ?: '-' }}
                                </td>
                                <td class="px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">
                                    <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $endorsement?->author_name ?: '-' }}</p>
                                    <p class="mt-1 line-clamp-2" title="{{ $endorsement?->book_title }}">{{ $endorsement?->book_title ?: '-' }}</p>
                                </td>
                                @unless ($isSalesDepartmentView)
                                    <td class="px-3 py-4">
                                        <span @class([
                                            'rounded-full px-2 py-1 text-[11px] font-semibold',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' => $endorsement?->contract_status === 'signed',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200' => $endorsement?->contract_status !== 'signed',
                                        ])>
                                            {{ $endorsement?->contract_status === 'signed' ? 'Signed' : 'Pending' }}
                                        </span>
                                    </td>
                                @endunless
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $endorsement?->services ?: '-' }}</td>
                                <td class="px-3 py-4">
                                    @if (($project->welcome_email_status ?? 'pending') === 'other_reason')
                                        <button type="button"
                                                x-on:click="openReason(@js($project->welcome_email_reason))"
                                                class="rounded-full bg-orange-100 px-2 py-1 text-[11px] font-semibold text-orange-700 underline dark:bg-orange-400/15 dark:text-orange-200">
                                            Other Reason
                                        </button>
                                    @else
                                        <span @class([
                                            'rounded-full px-2 py-1 text-[11px] font-semibold',
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200' => ($project->welcome_email_status ?? 'pending') === 'done',
                                            'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200' => ($project->welcome_email_status ?? 'pending') === 'pending',
                                        ])>
                                            {{ $welcomeLabels[$project->welcome_email_status ?? 'pending'] ?? 'Pending' }}
                                        </span>
                                    @endif
                                </td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">{{ $fulfillmentName }}</td>
                                <td class="px-3 py-4">
                                    <div class="text-[11px] font-semibold text-slate-700 dark:text-zinc-300">{{ $progress }}%</div>
                                    <div class="mt-2 h-2 rounded-full bg-slate-100 dark:bg-zinc-800">
                                        <div @class([
                                                'h-2 rounded-full',
                                                'bg-emerald-500' => $progress === 100,
                                                'bg-sky-500' => $progress > 0 && $progress < 100,
                                                'bg-slate-300 dark:bg-zinc-700' => $progress === 0,
                                            ])
                                            style="width: {{ $progress }}%"></div>
                                    </div>
                                    <div class="mt-2 text-[11px] font-semibold text-slate-500 dark:text-zinc-400">
                                        {{ $statusLabels[$project->status] ?? str($project->status)->replace('_', ' ')->title() }}
                                    </div>
                                </td>
                                @if ($showAgentColumn)
                                    <td class="break-words px-3 py-4 leading-snug text-slate-900 dark:text-zinc-100">
                                        <p class="font-semibold">{{ $agentName }}</p>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ ($canSelectProjects ? 12 : 11) - ($isSalesDepartmentView ? 1 : 0) - ($showAgentColumn ? 0 : 1) }}" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No production projects yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($projects->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $projects->links() }}
                </div>
            @endif
        </div>

        <div x-show="welcomeOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
             x-on:keydown.escape.window="welcomeOpen = false">
            <form method="POST" action="{{ route('production.projects.bulk-update') }}" class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                @csrf
                @method('PUT')
                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                <template x-for="projectId in selected" :key="projectId">
                    <input type="hidden" name="project_ids[]" x-bind:value="projectId">
                </template>

                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Change Welcome Email</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Update <span x-text="selected.length"></span> selected project(s).
                        </p>
                    </div>
                    <button type="button" x-on:click="welcomeOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        <span class="sr-only">Close</span>
                        &times;
                    </button>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Welcome Email Status</label>
                        <select name="welcome_email_status"
                                x-model="welcomeStatus"
                                class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            @foreach ($welcomeLabels as $welcomeKey => $welcomeLabel)
                                <option value="{{ $welcomeKey }}">{{ $welcomeLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="welcomeStatus === 'other_reason'" x-cloak>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Reason</label>
                        <textarea name="welcome_email_reason"
                                  rows="5"
                                  x-bind:required="welcomeStatus === 'other_reason'"
                                  x-model="welcomeReason"
                                  class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"></textarea>
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" x-on:click="welcomeOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                        Save
                    </button>
                </div>
            </form>
        </div>

        <div x-show="assignOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
             x-on:keydown.escape.window="assignOpen = false">
            <form method="POST" action="{{ route('production.projects.tasks.store') }}" class="flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-xl dark:bg-zinc-900">
                @csrf
                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                <input type="hidden" name="project_id" x-bind:value="activeProject.projectId">

                <div class="shrink-0 border-b border-slate-100 p-6 dark:border-zinc-800">
                    <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Production Task</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            Group service inclusions and assign the work to one Production member.
                        </p>
                    </div>
                    <button type="button" x-on:click="assignOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        <span class="sr-only">Close</span>
                        &times;
                    </button>
                    </div>
                </div>

                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-5">
                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Task Name</label>
                        <input type="text" name="title" x-model="taskTitle" required maxlength="255"
                               class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Assign To</label>
                        <select name="assigned_to"
                                required
                                class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="">Select production staff</option>
                            @foreach ($productionStaff as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->first_name }} {{ $staff->last_name }} - {{ $staff->role?->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div x-show="activeProject.items.length > 0" class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        <p class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Service Inclusions</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">Choose all inclusions this person will complete.</p>
                        <div class="mt-3 grid gap-2 sm:grid-cols-2">
                            <template x-for="item in activeProject.items" :key="item.id">
                                <label class="flex min-h-14 items-start gap-2 rounded-lg bg-slate-50 p-3 text-sm dark:bg-zinc-950"
                                       x-bind:class="item.assigned ? 'cursor-not-allowed opacity-50' : 'cursor-pointer'">
                                    <input type="checkbox" name="service_item_ids[]" x-bind:value="item.id"
                                           x-model="selectedTaskItems" x-bind:disabled="item.assigned"
                                           class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                    <span>
                                        <span class="font-medium text-slate-700 dark:text-zinc-200" x-text="item.name"></span>
                                        <span x-show="item.assigned" class="mt-0.5 block text-xs text-slate-500">Already assigned</span>
                                    </span>
                                </label>
                            </template>
                        </div>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Endorsement Instruction</label>
                        <textarea name="instructions" rows="4"
                                  class="mt-2 w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100"></textarea>
                    </div>

                    <div>
                        <label class="text-sm font-semibold text-slate-700 dark:text-zinc-200">Due Date <span class="font-normal text-slate-400">(optional)</span></label>
                        <x-date-picker name="due_date"
                                       class="mt-2 w-full rounded-xl border-slate-300 px-4 py-2 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                    </div>
                </div>

                <div class="shrink-0 border-t border-slate-100 bg-white px-6 py-4 dark:border-zinc-800 dark:bg-zinc-900">
                    <div class="flex justify-end gap-2">
                    <button type="button" x-on:click="assignOpen = false" class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-xl bg-zinc-950 px-5 py-2 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                        Create Task
                    </button>
                    </div>
                </div>
            </form>
        </div>

        <div x-show="detailOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
             x-on:keydown.escape.window="detailOpen = false">
            <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Project Details</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400" x-text="detail.projectId"></p>
                    </div>
                    <button type="button" x-on:click="detailOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        <span class="sr-only">Close</span>
                        &times;
                    </button>
                </div>

                <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <template x-for="item in [
                        ['Brand', detail.brand],
                        ['Tracker', detail.tracker],
                        ['Date Sold', detail.dateSold],
                        ['Author', detail.author],
                        ['Book Title', detail.bookTitle],
                        ['Agreement', detail.agreement],
                        ['Service', detail.service],
                        ['Welcome Email', detail.welcomeEmail],
                        ['Fulfillment Officer', detail.fulfillmentOfficer],
                        @unless ($isSalesDepartmentView)
                            ['Production Team', detail.assignedTo],
                        @endunless
                        ['Project Status', detail.projectStatus],
                        ['Progress', detail.progress],
                        @if ($showAgentColumn)
                            ['Agent', detail.agent],
                        @endif
                        ['Contact', detail.contact],
                        ['Email', detail.email],
                        ['Mailing Address', detail.address],
                        ['Welcome Email Reason', detail.welcomeReason],
                        ['Endorsement Notes', detail.endorsementNotes],
                        ['Endorsement Instruction', detail.assignmentInstruction],
                        ['Production Notes', detail.productionNotes],
                    ]">
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-[11px] font-semibold uppercase text-slate-400 dark:text-zinc-500" x-text="item[0]"></p>
                            <p class="mt-2 whitespace-pre-line text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="item[1] || '-'"></p>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div x-show="reasonOpen"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4"
             x-on:keydown.escape.window="reasonOpen = false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Welcome Email Reason</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Reason saved for this project.</p>
                    </div>
                    <button type="button" x-on:click="reasonOpen = false" class="text-slate-500 hover:text-slate-900 dark:text-zinc-400 dark:hover:text-zinc-100">
                        <span class="sr-only">Close</span>
                        &times;
                    </button>
                </div>

                <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm leading-relaxed text-slate-700 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200" x-text="reasonText"></div>
            </div>
        </div>
    </div>
</x-app-layout>
