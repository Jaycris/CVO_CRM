<x-app-layout>
    <x-slot name="header">
        Leads
    </x-slot>

    <div class="space-y-6">
        @php
            $currentViewMode = $viewMode ?? 'all';
            $roleName = auth()->user()->role?->name;
            $departmentName = auth()->user()->department;
            $isAdmin = $roleName === 'Admin';
            $isAssignedAgentReadOnly = $currentViewMode === 'assigned' && $departmentName === 'Sales' && ! $isAdmin;
            $showSelectionControls = ! $isAssignedAgentReadOnly;
            $canSelfMineLead = $departmentName === 'Sales' && auth()->user()->hasPermission('self_mine_work_leads');
            $canCreateLead = $isAdmin || auth()->user()->hasPermission('create_leads') || $canSelfMineLead;
            $canEditLead = ! $isAssignedAgentReadOnly && ($isAdmin || auth()->user()->hasPermission('edit_leads'));
            $canDeleteLead = ! $isAssignedAgentReadOnly && ($isAdmin || auth()->user()->hasPermission('delete_leads'));
            $canAssignLead = ! $isAssignedAgentReadOnly && ($isAdmin || auth()->user()->hasPermission('assign_leads'));
            $canReassignTeamLeads = $isAdmin || auth()->user()->hasPermission('reassign_team_leads');
            $canUnassignTeamLeads = $isAdmin || auth()->user()->hasPermission('unassign_team_leads');
            $canArchiveLead = ! $isAssignedAgentReadOnly && ($isAdmin || auth()->user()->hasPermission('archive_leads'));
            $canVerifyLead = ! $isAssignedAgentReadOnly && ($isAdmin || auth()->user()->hasPermission('verify_leads'));
            $showSalesWorkflowActions = ! $isAssignedAgentReadOnly
                && ($isAdmin || auth()->user()->hasPermission('move_sales_stage') || auth()->user()->hasPermission('return_leads'))
                && ($currentViewMode === 'new_assigned' || $currentViewMode === 'assigned' || str_starts_with($currentViewMode, 'sales_'));
            $showMoveSalesStageAction = $isAdmin || auth()->user()->hasPermission('move_sales_stage');
            $showReturnLeadAction = $isAdmin || auth()->user()->hasPermission('return_leads');
            $showSendToVerificationAction = ($isAdmin || auth()->user()->hasPermission('send_leads_to_verification'))
                && in_array($currentViewMode, ['all', 'mine', 'returned'], true);
            $showMoveVerifiedToReadyAction = ($isAdmin || auth()->user()->hasPermission('move_verified_leads_to_ready'))
                && $currentViewMode === 'verification_queue';
            $showSendReturnedBackAction = ($isAdmin || auth()->user()->hasPermission('send_returned_leads_back')) && $currentViewMode === 'returned';
            $showArchiveAction = $canArchiveLead && $currentViewMode !== 'archived';
            $showRestoreAction = $canArchiveLead && $currentViewMode === 'archived';
            $isSalesPage = str_starts_with($currentViewMode, 'sales_');
            $isTeamLeadsPage = $currentViewMode === 'sales_team_leads';
            $canOpenAssignModal = $canAssignLead || ($isTeamLeadsPage && $canReassignTeamLeads);
            $canOpenUnassignAction = $canAssignLead || ($isTeamLeadsPage && $canUnassignTeamLeads);
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">{{ $pageTitle ?? 'Leads' }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    {{ $pageDescription ?? 'Track mined author leads, assigned team members, and verification scores.' }}
                </p>
            </div>

            @if ($canCreateLead && ($viewMode ?? 'all') === 'mine')
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('leads.import', ['return_to' => request()->fullUrl()]) }}"
                       class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm font-semibold text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                        Import CSV
                    </a>
                    <a href="{{ route('leads.create', ['return_to' => request()->fullUrl()]) }}"
                       class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                        + Add Lead
                    </a>
                </div>
            @elseif ($canSelfMineLead && ($viewMode ?? 'all') === 'new_assigned')
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('leads.create', ['return_to' => request()->fullUrl()]) }}"
                       class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                        + Add Lead
                    </a>
                </div>
            @endif
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

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-6">
            @foreach ($summaryCards as $card)
                <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <p class="text-sm text-slate-500 dark:text-zinc-400">{{ $card['label'] }}</p>
                    <h3 class="mt-3 text-3xl font-bold text-slate-900 dark:text-zinc-100">
                        @if (($card['format'] ?? null) === 'currency')
                            ${{ number_format((float) $card['count'], 2) }}
                        @else
                            {{ number_format($card['count']) }}
                        @endif
                    </h3>
                    <p @class([
                        'mt-2 text-sm',
                        'text-amber-600 dark:text-amber-300' => $card['tone'] === 'amber',
                        'text-rose-600 dark:text-rose-300' => $card['tone'] === 'rose',
                        'text-emerald-600 dark:text-emerald-300' => $card['tone'] === 'emerald',
                        'text-sky-600 dark:text-sky-300' => $card['tone'] === 'sky',
                        'text-violet-600 dark:text-violet-300' => $card['tone'] === 'violet',
                        'text-slate-600 dark:text-zinc-300' => $card['tone'] === 'slate',
                    ])>
                        {{ $card['hint'] }}
                    </p>
                </div>
            @endforeach
        </div>

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
             x-data="{
                detailsModalOpen: false,
                assignModalOpen: false,
                workflowModalOpen: false,
                selectedLead: {},
                selectedLeadIds: [],
                selectedEditUrl: '',
                selectedVerifyUrl: '',
                selectedDeleteUrl: '',
                selectedLeadAssignments: {},
                hasSelection() {
                    return this.selectedLeadIds.length > 0;
                },
                hasOnlyUnassignedSelection() {
                    return this.hasSelection() && this.selectedLeadIds.every((leadId) => !this.selectedLeadAssignments[leadId]);
                },
                hasOnlyAssignedSelection() {
                    return this.hasSelection() && this.selectedLeadIds.every((leadId) => this.selectedLeadAssignments[leadId]);
                },
                hasSingleSelection() {
                    return this.selectedLeadIds.length === 1;
                },
                isSelected(id) {
                    return this.selectedLeadIds.includes(id);
                },
                syncSingleLeadSelection(visibleLeads) {
                    if (this.selectedLeadIds.length === 1) {
                        const selectedLead = visibleLeads.find((lead) => lead.id === this.selectedLeadIds[0]);
                        this.selectedEditUrl = selectedLead?.editUrl || '';
                        this.selectedVerifyUrl = selectedLead?.verifyUrl || '';
                        this.selectedDeleteUrl = selectedLead?.deleteUrl || '';
                        return;
                    }

                    this.selectedEditUrl = '';
                    this.selectedVerifyUrl = '';
                    this.selectedDeleteUrl = '';
                },
                allVisibleLeadsSelected(visibleLeads) {
                    const visibleIds = visibleLeads.map((lead) => lead.id);

                    return visibleIds.length > 0 && visibleIds.every((id) => this.selectedLeadIds.includes(id));
                },
                toggleVisibleLeads(visibleLeads) {
                    const visibleIds = visibleLeads.map((lead) => lead.id);
                    const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => this.selectedLeadIds.includes(id));

                    if (allVisibleSelected) {
                        this.selectedLeadIds = this.selectedLeadIds.filter((id) => !visibleIds.includes(id));
                        visibleIds.forEach((id) => delete this.selectedLeadAssignments[id]);
                    } else {
                        visibleLeads.forEach((lead) => {
                            if (!this.selectedLeadIds.includes(lead.id)) {
                                this.selectedLeadIds.push(lead.id);
                            }

                            this.selectedLeadAssignments[lead.id] = lead.assigned;
                        });
                    }

                    this.syncSingleLeadSelection(visibleLeads);
                },
                selectLead(id, editUrl, verifyUrl, deleteUrl, isAssigned) {
                    if (this.isSelected(id)) {
                        this.selectedLeadIds = this.selectedLeadIds.filter((selectedId) => selectedId !== id);
                        delete this.selectedLeadAssignments[id];
                    } else {
                        this.selectedLeadIds.push(id);
                        this.selectedLeadAssignments[id] = isAssigned;
                    }

                    if (this.selectedLeadIds.length === 1) {
                        this.selectedEditUrl = editUrl;
                        this.selectedVerifyUrl = verifyUrl;
                        this.selectedDeleteUrl = deleteUrl;
                    } else {
                        this.selectedEditUrl = '';
                        this.selectedVerifyUrl = '';
                        this.selectedDeleteUrl = '';
                    }
                }
             }">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $pageTitle ?? 'Lead Generation' }} Directory</h2>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-2">
                            @if ($canEditLead)
                                <a x-bind:href="hasSingleSelection() ? selectedEditUrl : '#'"
                                   x-bind:class="hasSingleSelection() ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                   x-bind:aria-disabled="(!hasSingleSelection()).toString()"
                                   title="Edit selected lead"
                                   class="inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                    </svg>
                                </a>
                            @endif

                            @if ($canOpenAssignModal)
                                <button type="button"
                                        x-on:click="if (({{ $canAssignLead ? 'true' : 'false' }} && hasOnlyUnassignedSelection()) || ({{ $canReassignTeamLeads && $isTeamLeadsPage ? 'true' : 'false' }} && hasOnlyAssignedSelection())) assignModalOpen = true"
                                        x-bind:disabled="!(({{ $canAssignLead ? 'true' : 'false' }} && hasOnlyUnassignedSelection()) || ({{ $canReassignTeamLeads && $isTeamLeadsPage ? 'true' : 'false' }} && hasOnlyAssignedSelection()))"
                                        x-bind:class="(({{ $canAssignLead ? 'true' : 'false' }} && hasOnlyUnassignedSelection()) || ({{ $canReassignTeamLeads && $isTeamLeadsPage ? 'true' : 'false' }} && hasOnlyAssignedSelection())) ? 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200 dark:hover:bg-sky-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                        x-bind:title="{{ $isTeamLeadsPage ? "'Select assigned team leads to reassign'" : "'Select only unassigned leads to assign'" }}"
                                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21a7.5 7.5 0 0 1 15 0" />
                                    </svg>
                            </button>
                            @endif

                            @if ($canOpenUnassignAction)
                                <form method="POST"
                                      action="{{ route('leads.unassign') }}"
                                      x-on:submit="if (!hasOnlyAssignedSelection() || !confirm('Unassign selected leads?')) { $event.preventDefault(); }">
                                    @csrf
                                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                    <template x-for="leadId in selectedLeadIds" :key="`unassign-${leadId}`">
                                        <input type="hidden" name="lead_ids[]" :value="leadId">
                                    </template>

                                    <button type="submit"
                                            x-bind:disabled="!hasOnlyAssignedSelection()"
                                            x-bind:class="hasOnlyAssignedSelection() ? 'border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 dark:border-orange-400/30 dark:bg-orange-400/10 dark:text-orange-200 dark:hover:bg-orange-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                            x-bind:title="hasOnlyAssignedSelection() ? 'Unassign selected leads' : 'Select only assigned leads to unassign'"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 12H15" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21a7.5 7.5 0 0 1 15 0" />
                                        </svg>
                                    </button>
                                </form>
                            @endif

                            @if ($showSelectionControls)
                            <button type="button"
                                    x-on:click="if (hasSelection()) workflowModalOpen = true"
                                    x-bind:disabled="!hasSelection()"
                                    x-bind:class="hasSelection() ? 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 dark:border-violet-400/30 dark:bg-violet-400/10 dark:text-violet-200 dark:hover:bg-violet-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Move selected leads"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m13.5 6 6 6-6 6" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6h3m-3 12h3" />
                                </svg>
                            </button>
                            @endif

                            @if ($canVerifyLead)
                                <a x-bind:href="hasSingleSelection() ? selectedVerifyUrl : '#'"
                                   x-bind:class="hasSingleSelection() ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                   x-bind:aria-disabled="(!hasSingleSelection()).toString()"
                                   title="Verify selected lead"
                                   class="inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m9 12.75 2.25 2.25L15 9.75" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3.75 4.5 7.125v5.625c0 4.142 3.358 7.5 7.5 7.5s7.5-3.358 7.5-7.5V7.125L12 3.75Z" />
                                    </svg>
                                </a>
                            @endif

                            @if ($canDeleteLead)
                                <form method="POST"
                                      action="{{ route('leads.bulk-destroy') }}"
                                      x-on:submit="if (!hasSelection() || !confirm(selectedLeadIds.length === 1 ? 'Delete this lead?' : `Delete ${selectedLeadIds.length} selected leads?`)) { $event.preventDefault(); }">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                    <template x-for="leadId in selectedLeadIds" :key="`delete-${leadId}`">
                                        <input type="hidden" name="lead_ids[]" :value="leadId">
                                    </template>

                                    <button type="submit"
                                            x-bind:disabled="!hasSelection()"
                                            x-bind:class="hasSelection() ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                            x-bind:title="hasSelection() ? (selectedLeadIds.length === 1 ? 'Delete selected lead' : 'Delete selected leads') : 'Select leads to delete'"
                                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166M19.228 5.79 18.16 19.673A2.25 2.25 0 0 1 15.916 21.75H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .563c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form method="GET" action="{{ url()->current() }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search leads..."
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500 sm:w-56">
                            @unless ($isSalesPage)
                                <select name="assignment_status"
                                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 sm:w-44">
                                    <option value="">All assignment</option>
                                    <option value="assigned" @selected(request('assignment_status') === 'assigned')>Assigned</option>
                                    <option value="unassigned" @selected(request('assignment_status') === 'unassigned')>Unassigned</option>
                                </select>
                            @endunless
                            @unless ($isSalesPage || $currentViewMode === 'all' || $currentViewMode === 'mine')
                                <select name="verification_status"
                                        class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 sm:w-44">
                                    <option value="">All verification</option>
                                    <option value="verified" @selected(request('verification_status') === 'verified')>Verified</option>
                                    <option value="unverified" @selected(request('verification_status') === 'unverified')>Unverified</option>
                                </select>
                            @endunless
                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>
                            @if (request('search') || request('assignment_status') || request('verification_status'))
                                <a href="{{ url()->current() }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div>
                @php
                    $visibleLeadSelections = $leads->map(fn ($lead) => [
                        'id' => $lead->id,
                        'editUrl' => route('leads.edit', ['lead' => $lead, 'return_to' => request()->fullUrl()]),
                        'verifyUrl' => route('leads.verify', ['lead' => $lead, 'return_to' => request()->fullUrl()]),
                        'deleteUrl' => route('leads.destroy', $lead),
                        'assigned' => ! is_null($lead->assigned_to),
                    ])->values();
                @endphp
                <table class="w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            @if ($showSelectionControls)
                            <th class="w-[4%] px-3 py-4">
                                <input type="checkbox"
                                       class="rounded border-slate-300 text-amber-600 focus:ring-amber-500"
                                       x-bind:checked="allVisibleLeadsSelected(@js($visibleLeadSelections))"
                                       x-on:change="toggleVisibleLeads(@js($visibleLeadSelections))">
                            </th>
                            @endif
                            <th class="w-[8%] px-3 py-4">Publisher</th>
                            <th class="w-[14%] px-3 py-4">Book Title</th>
                            <th class="w-[10%] px-3 py-4">Author's Name</th>
                            <th class="w-[11%] px-3 py-4">Phone Number</th>
                            <th class="w-[10%] px-3 py-4">Email</th>
                            <th class="w-[7%] px-3 py-4">Book Link</th>
                            <th class="w-[8%] px-3 py-4">Published Date</th>
                            <th class="w-[9%] px-3 py-4">Assigned To</th>
                            <th class="w-[8%] px-3 py-4">Assigned Date</th>
                            <th class="w-[11%] px-3 py-4">Verify Score</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($leads as $lead)
                            <tr @if ($showSelectionControls) x-on:click="selectLead({{ $lead->id }}, @js(route('leads.edit', ['lead' => $lead, 'return_to' => request()->fullUrl()])), @js(route('leads.verify', ['lead' => $lead, 'return_to' => request()->fullUrl()])), @js(route('leads.destroy', $lead)), @js(! is_null($lead->assigned_to)))" @endif
                                x-bind:class="isSelected({{ $lead->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                @class(['cursor-pointer' => $showSelectionControls])>
                                @if ($showSelectionControls)
                                <td class="px-3 py-4">
                                    <input type="checkbox"
                                           name="selected_leads[]"
                                           value="{{ $lead->id }}"
                                           x-bind:checked="isSelected({{ $lead->id }})"
                                           x-on:click.stop="selectLead({{ $lead->id }}, @js(route('leads.edit', ['lead' => $lead, 'return_to' => request()->fullUrl()])), @js(route('leads.verify', ['lead' => $lead, 'return_to' => request()->fullUrl()])), @js(route('leads.destroy', $lead)), @js(! is_null($lead->assigned_to)))"
                                           class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                </td>
                                @endif
                                <td class="break-words px-3 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $lead->publisher ?: '-' }}
                                </td>
                                <td class="px-3 py-4">
                                    <button type="button"
                                            x-on:click="selectedLead = @js([
                                                'publisher' => $lead->publisher ?: '-',
                                                'bookTitle' => $lead->book_title,
                                                'authorName' => $lead->author_name,
                                                'phoneNumbers' => $lead->phone_numbers ?? [],
                                                'verifiedPhoneNumbers' => $lead->verified_phone_numbers ?? [],
                                                'phoneNumberStatuses' => $lead->phone_number_statuses ?? [],
                                                'phoneStatusUrl' => route('leads.phone-statuses', $lead),
                                                'email' => $lead->email ?: '-',
                                                'bookLink' => $lead->book_link,
                                                'publishedDate' => $lead->published_date?->format('M d, Y') ?: '-',
                                                'assignedTo' => $lead->assignedUser ? trim($lead->assignedUser->first_name . ' ' . $lead->assignedUser->last_name) : 'Unassigned',
                                                'assignedDate' => $lead->assigned_date?->format('M d, Y') ?: '-',
                                                'previousAgent' => $lead->previousAgent ? trim($lead->previousAgent->first_name . ' ' . $lead->previousAgent->last_name) : '-',
                                                'previousAgentReleasedAt' => $lead->previous_agent_released_at?->format('M d, Y h:i A') ?: '-',
                                                'previousAgentReleaseReason' => $lead->previous_agent_release_reason ?: '-',
                                                'assignmentHistory' => $lead->assignmentHistories->map(fn ($history) => [
                                                    'agent' => $history->agent ? trim($history->agent->first_name . ' ' . $history->agent->last_name) : 'Deleted user',
                                                    'assignedBy' => $history->assignedBy ? trim($history->assignedBy->first_name . ' ' . $history->assignedBy->last_name) : 'System / existing record',
                                                    'assignedAt' => $history->assigned_at?->format('M d, Y h:i A') ?: '-',
                                                    'releasedAt' => $history->released_at?->format('M d, Y h:i A') ?: 'Current assignment',
                                                    'releaseReason' => $history->release_reason ?: '-',
                                                ])->values(),
                                                'verifyScore' => is_null($lead->verify_score) ? 'Not verified' : $lead->verify_score . '/100',
                                                'addedBy' => $lead->createdBy ? trim($lead->createdBy->first_name . ' ' . $lead->createdBy->last_name) : '-',
                                                'addedAt' => $lead->created_at?->format('M d, Y h:i A') ?: '-',
                                                'verifiedBy' => $lead->verifiedBy ? trim($lead->verifiedBy->first_name . ' ' . $lead->verifiedBy->last_name) : '-',
                                                'verifiedAt' => $lead->verified_at?->format('M d, Y h:i A') ?: '-',
                                                'salesStage' => match ($lead->sales_stage) {
                                                    'pipeline' => 'Pipeline',
                                                    'prospect' => 'Prospect',
                                                    'scheduled_callback' => 'Scheduled Callback',
                                                    'sold' => 'Sold',
                                                    'refunds' => 'Refunds',
                                                    default => '-',
                                                },
                                                'salesStageUpdatedAt' => $lead->sales_stage_updated_at?->format('M d, Y h:i A') ?: '-',
                                                'returnedBy' => $lead->returnedBy ? trim($lead->returnedBy->first_name . ' ' . $lead->returnedBy->last_name) : '-',
                                                'returnedAt' => $lead->returned_at?->format('M d, Y h:i A') ?: '-',
                                                'returnNotes' => $lead->return_notes ?: '-',
                                                'archivedAt' => $lead->archived_at?->format('M d, Y h:i A') ?: '-',
                                            ]); detailsModalOpen = true"
                                            title="View lead details"
                                            class="block max-w-full text-left font-semibold leading-snug text-slate-900 hover:text-amber-700 dark:text-zinc-100 dark:hover:text-amber-300">
                                        <span class="line-clamp-2">{{ $lead->book_title }}</span>
                                    </button>
                                </td>
                                <td class="break-words px-3 py-4 text-slate-700 dark:text-zinc-200">
                                    {{ $lead->author_name }}
                                </td>
                                <td class="px-3 py-4">
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($lead->phone_numbers ?? [] as $phoneNumber)
                                            @php
                                                $phoneStatus = in_array($phoneNumber, $lead->verified_phone_numbers ?? [], true)
                                                    ? 'Verified'
                                                    : (($lead->phone_number_statuses ?? [])[$phoneNumber] ?? null);
                                                $phoneStatusClass = match ($phoneStatus) {
                                                    'Verified' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200',
                                                    'Voice Mail' => 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200',
                                                    'No Answer' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-200',
                                                    'NIS' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200',
                                                    'DNC' => 'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-200',
                                                    'Wrong Number' => 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-200',
                                                    default => 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200',
                                                };
                                            @endphp
                                            <span title="{{ $phoneStatus ? 'Status: '.$phoneStatus : 'Status: Not verified' }}"
                                                  class="max-w-full break-words rounded-full px-2 py-1 text-[11px] font-semibold leading-tight {{ $phoneStatusClass }}">
                                                {{ $phoneNumber }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-4 text-slate-600 dark:text-zinc-300">
                                    @if ($lead->email)
                                        <span class="block truncate" title="{{ $lead->email }}">
                                            {{ $lead->email }}
                                        </span>
                                    @else
                                        <span>-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4">
                                    @if ($lead->book_link)
                                        <a href="{{ $lead->book_link }}" target="_blank" rel="noreferrer"
                                           class="font-semibold text-amber-700 hover:text-amber-900 dark:text-amber-300">
                                            Open
                                        </a>
                                    @else
                                        <span class="text-slate-400 dark:text-zinc-500">-</span>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $lead->published_date?->format('M d, Y') ?: '-' }}
                                </td>
                                <td class="px-3 py-4">
                                    @if ($lead->assignedUser)
                                        <span class="block max-w-full break-words rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold leading-tight text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {{ $lead->assignedUser->first_name }} {{ $lead->assignedUser->last_name }}
                                        </span>
                                    @else
                                        <div class="space-y-1">
                                            <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                                Unassigned
                                            </span>
                                            @if ($lead->previousAgent)
                                                <p class="text-[11px] font-semibold text-amber-700 dark:text-amber-300">
                                                    Previous: {{ $lead->previousAgent->first_name }} {{ $lead->previousAgent->last_name }}
                                                </p>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-3 py-4 text-slate-600 dark:text-zinc-300">
                                    {{ $lead->assigned_date?->format('M d, Y') ?: '-' }}
                                </td>
                                <td class="px-3 py-4">
                                    @if (! is_null($lead->verify_score))
                                        <div class="space-y-1">
                                            <span class="rounded-full bg-emerald-100 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200">
                                                {{ $lead->verify_score }}/100
                                            </span>
                                            @if ($lead->verification_notes)
                                                <p class="break-words text-[11px] text-slate-500 dark:text-zinc-400">{{ $lead->verification_notes }}</p>
                                            @endif
                                        </div>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                                            Not verified
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $showSelectionControls ? 11 : 10 }}" class="px-6 py-16 text-center">
                                    <div class="mx-auto max-w-md">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h.01M3 12h.01M3 18h.01" />
                                            </svg>
                                        </div>
                                        <h3 class="mt-4 font-semibold text-slate-900 dark:text-zinc-100">No leads yet</h3>
                                        <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                            Add the first lead to start tracking author contact details and verification scores.
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($leads->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $leads->links() }}
                </div>
            @endif

            <div x-show="assignModalOpen"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
                 x-on:keydown.escape.window="assignModalOpen = false">
                <div x-on:click.outside="assignModalOpen = false"
                     class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Assign Leads</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                @if ($isTeamLeadsPage)
                                    Reassign <span class="font-semibold" x-text="selectedLeadIds.length"></span> selected team lead(s) to one Sales department user.
                                @else
                                    Assign <span class="font-semibold" x-text="selectedLeadIds.length"></span> selected unassigned lead(s) to one Sales department user.
                                @endif
                            </p>
                        </div>

                        <button type="button"
                                x-on:click="assignModalOpen = false"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('leads.assign') }}" class="mt-6 space-y-5">
                        @csrf
                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                        <template x-for="leadId in selectedLeadIds" :key="leadId">
                            <input type="hidden" name="lead_ids[]" :value="leadId">
                        </template>

                        <div>
                            <label for="assigned_to" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Sales Department User
                            </label>
                            <select id="assigned_to"
                                    name="assigned_to"
                                    required
                                    class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                <option value="">Select a Sales user</option>
                                @foreach ($salesAssignees as $salesAssignee)
                                    <option value="{{ $salesAssignee->id }}">
                                        {{ $salesAssignee->first_name }} {{ $salesAssignee->last_name }} - {{ $salesAssignee->role?->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                        </div>

                        @if ($salesAssignees->isEmpty())
                            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                                No Sales department users are available yet.
                            </div>
                        @endif

                        <div class="flex items-center justify-end gap-3">
                            <button type="button"
                                    x-on:click="assignModalOpen = false"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Cancel
                            </button>

                            <button type="submit"
                                    @disabled($salesAssignees->isEmpty())
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400 dark:bg-amber-400 dark:text-zinc-950 dark:disabled:bg-zinc-800 dark:disabled:text-zinc-500">
                                {{ $isTeamLeadsPage ? 'Reassign Leads' : 'Assign Leads' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div x-show="workflowModalOpen"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
                 x-on:keydown.escape.window="workflowModalOpen = false">
                <div x-on:click.outside="workflowModalOpen = false"
                     class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Lead Actions</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                Choose an action for <span class="font-semibold" x-text="selectedLeadIds.length"></span> selected lead(s).
                            </p>
                        </div>

                        <button type="button"
                                x-on:click="workflowModalOpen = false"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    @if ($showMoveSalesStageAction && $showSalesWorkflowActions)
                        <form method="POST" action="{{ route('leads.sales-stage') }}" class="mt-6">
                            @csrf
                            <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                            <template x-for="leadId in selectedLeadIds" :key="`stage-${leadId}`">
                                <input type="hidden" name="lead_ids[]" :value="leadId">
                            </template>

                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <button type="submit" name="sales_stage" value="pipeline"
                                        class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:border-amber-200 hover:bg-amber-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-amber-400/30 dark:hover:bg-amber-400/10">
                                    Pipeline
                                </button>
                                <button type="submit" name="sales_stage" value="prospect"
                                        class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:border-amber-200 hover:bg-amber-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-amber-400/30 dark:hover:bg-amber-400/10">
                                    Prospect
                                </button>
                                <button type="submit" name="sales_stage" value="scheduled_callback"
                                        class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-left text-sm font-semibold text-slate-700 hover:border-amber-200 hover:bg-amber-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-amber-400/30 dark:hover:bg-amber-400/10">
                                    Scheduled Callback
                                </button>
                                <button type="submit" name="sales_stage" value="sold"
                                        class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-left text-sm font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/20">
                                    Sold
                                </button>
                            </div>
                        </form>
                    @endif

                    <div class="mt-5 grid grid-cols-1 justify-center gap-3 sm:grid-cols-[minmax(0,250px)] lg:grid-cols-[repeat(auto-fit,minmax(250px,250px))]">
                        @if ($showSendToVerificationAction)
                            <form method="POST" action="{{ route('leads.send-to-verification') }}"
                                  x-on:submit="if (!confirm('Send selected leads to Verification Queue?')) { $event.preventDefault(); }"
                                  class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                <template x-for="leadId in selectedLeadIds" :key="`verify-queue-${leadId}`">
                                    <input type="hidden" name="lead_ids[]" :value="leadId">
                                </template>

                                <h4 class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Send to Verification</h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                    Move selected leads into the verifier queue for checking.
                                </p>
                                <button type="submit"
                                        class="mt-5 flex min-h-12 w-full items-center justify-center rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-center text-sm font-semibold text-sky-700 hover:bg-sky-100 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-200 dark:hover:bg-sky-400/20">
                                    Send to Verification Queue
                                </button>
                            </form>
                        @endif

                        @if ($showMoveVerifiedToReadyAction)
                            <form method="POST" action="{{ route('leads.move-verified-to-ready') }}"
                                  x-on:submit="if (!confirm('Move selected verified leads to the next queue?')) { $event.preventDefault(); }"
                                  class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                <template x-for="leadId in selectedLeadIds" :key="`ready-queue-${leadId}`">
                                    <input type="hidden" name="lead_ids[]" :value="leadId">
                                </template>

                                <h4 class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Move to Ready Queue</h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                    Verified new leads go to Unassigned Leads. Verified returned leads go back to Returned Leads.
                                </p>
                                <button type="submit"
                                        class="mt-5 flex min-h-12 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/20">
                                    Move to Ready Queue
                                </button>
                            </form>
                        @endif

                        @if ($showReturnLeadAction && $showSalesWorkflowActions)
                            <form method="POST" action="{{ route('leads.return') }}" class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                <template x-for="leadId in selectedLeadIds" :key="`return-${leadId}`">
                                    <input type="hidden" name="lead_ids[]" :value="leadId">
                                </template>

                                <label for="return_notes" class="mb-2 block text-sm font-semibold text-slate-900 dark:text-zinc-100">
                                    Return Lead
                                </label>
                                <textarea id="return_notes" name="return_notes" rows="3"
                                          placeholder="Optional return notes"
                                          class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500"></textarea>
                                <button type="submit"
                                        class="mt-3 w-full rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20">
                                    Move to Returned Leads
                                </button>
                            </form>
                        @endif

                        @if ($showSendReturnedBackAction)
                            <form method="POST" action="{{ route('leads.send-returned-to-agent') }}"
                                  x-on:submit="if (!confirm('Send selected returned leads back to the assigned agent?')) { $event.preventDefault(); }"
                                  class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                <template x-for="leadId in selectedLeadIds" :key="`send-back-${leadId}`">
                                    <input type="hidden" name="lead_ids[]" :value="leadId">
                                </template>

                                <h4 class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Send Back to Agent</h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                    Use this after the returned lead has been fixed and verified again.
                                </p>
                                <button type="submit"
                                        class="mt-5 flex min-h-12 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/20">
                                    Send Back to Agent
                                </button>
                            </form>
                        @endif

                        @if ($showArchiveAction)
                            <form method="POST" action="{{ route('leads.archive') }}"
                                  x-on:submit="if (!confirm('Archive selected leads?')) { $event.preventDefault(); }"
                                  class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                <template x-for="leadId in selectedLeadIds" :key="`archive-${leadId}`">
                                    <input type="hidden" name="lead_ids[]" :value="leadId">
                                </template>

                                <h4 class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Archive Lead</h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                    Archived leads move out of active queues and sales workflows.
                                </p>
                                <button type="submit"
                                        class="mt-5 flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-slate-200 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700">
                                    Move to Archive
                                </button>
                            </form>
                        @endif

                        @if ($showRestoreAction)
                            <form method="POST" action="{{ route('leads.restore') }}"
                                  x-on:submit="if (!confirm('Move selected leads back to Leads?')) { $event.preventDefault(); }"
                                  class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                                <template x-for="leadId in selectedLeadIds" :key="`restore-${leadId}`">
                                    <input type="hidden" name="lead_ids[]" :value="leadId">
                                </template>

                                <h4 class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Move to Leads</h4>
                                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                                    Return selected leads to the active lead-generation pool.
                                </p>
                                <button type="submit"
                                        class="mt-5 flex min-h-12 w-full items-center justify-center rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/20">
                                    Move to Leads
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div x-show="detailsModalOpen"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
                 x-on:keydown.escape.window="detailsModalOpen = false">
                <div x-on:click.outside="detailsModalOpen = false"
                     class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Lead Details</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Full information for this lead.</p>
                        </div>
                        <button type="button"
                                x-on:click="detailsModalOpen = false"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800"
                                aria-label="Close">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Publisher</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.publisher"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Author's Name</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.authorName"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 md:col-span-2 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Book Title</p>
                            <p class="mt-1 text-sm font-semibold leading-6 text-slate-900 dark:text-zinc-100" x-text="selectedLead.bookTitle"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Phone Number</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <template x-for="phoneNumber in selectedLead.phoneNumbers" :key="phoneNumber">
                                    <span class="inline-flex flex-wrap items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold"
                                          x-bind:title="`Status: ${selectedLead.phoneNumberStatuses?.[phoneNumber] || ((selectedLead.verifiedPhoneNumbers || []).includes(phoneNumber) ? 'Verified' : 'Not verified')}`"
                                          x-bind:class="(selectedLead.verifiedPhoneNumbers || []).includes(phoneNumber)
                                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200'
                                            : (selectedLead.phoneNumberStatuses?.[phoneNumber] === 'Voice Mail'
                                                ? 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200'
                                                : (selectedLead.phoneNumberStatuses?.[phoneNumber] === 'No Answer'
                                                    ? 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-200'
                                                    : (selectedLead.phoneNumberStatuses?.[phoneNumber] === 'NIS'
                                                        ? 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200'
                                                        : (selectedLead.phoneNumberStatuses?.[phoneNumber] === 'DNC'
                                                            ? 'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-200'
                                                            : (selectedLead.phoneNumberStatuses?.[phoneNumber] === 'Wrong Number'
                                                                ? 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-200'
                                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200')))))">
                                        <span x-text="phoneNumber"></span>
                                        <span x-show="selectedLead.phoneNumberStatuses?.[phoneNumber] || (selectedLead.verifiedPhoneNumbers || []).includes(phoneNumber)"
                                              class="rounded-full bg-white/70 px-2 py-0.5 text-[10px] dark:bg-zinc-950/60"
                                              x-text="selectedLead.phoneNumberStatuses?.[phoneNumber] || 'Verified'"></span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Email</p>
                            <p class="mt-1 break-words text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.email"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Book Link</p>
                            <template x-if="selectedLead.bookLink">
                                <a :href="selectedLead.bookLink" target="_blank" rel="noreferrer"
                                   class="mt-1 inline-block text-sm font-semibold text-amber-700 hover:text-amber-900 dark:text-amber-300">
                                    Open Book Link
                                </a>
                            </template>
                            <template x-if="!selectedLead.bookLink">
                                <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100">-</p>
                            </template>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Published Date</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.publishedDate"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Assigned To</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.assignedTo"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Assigned Date</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.assignedDate"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Previous Agent</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.previousAgent"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="selectedLead.previousAgentReleasedAt"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="selectedLead.previousAgentReleaseReason"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 md:col-span-2 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Assignment History</p>
                            <div class="mt-3 space-y-2">
                                <template x-for="(history, index) in (selectedLead.assignmentHistory || [])" :key="`${history.agent}-${history.assignedAt}-${index}`">
                                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm dark:border-zinc-800 dark:bg-zinc-900">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-semibold text-slate-900 dark:text-zinc-100" x-text="history.agent"></p>
                                            <span class="text-xs font-medium text-slate-500 dark:text-zinc-400" x-text="history.releasedAt"></span>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                                            Assigned <span x-text="history.assignedAt"></span> by <span x-text="history.assignedBy"></span>
                                        </p>
                                        <p x-show="history.releaseReason !== '-'" class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="history.releaseReason"></p>
                                    </div>
                                </template>
                                <p x-show="!(selectedLead.assignmentHistory || []).length" class="text-sm text-slate-500 dark:text-zinc-400">
                                    No assignment history recorded yet.
                                </p>
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Verify Score</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.verifyScore"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Added By</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.addedBy"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="selectedLead.addedAt"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Verified By</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.verifiedBy"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="selectedLead.verifiedAt"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Sales Stage</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.salesStage"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="selectedLead.salesStageUpdatedAt"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Returned By</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.returnedBy"></p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400" x-text="selectedLead.returnedAt"></p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Archived Date</p>
                            <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="selectedLead.archivedAt"></p>
                        </div>

                        @if ($canEditLead)
                            <form method="POST"
                                  x-bind:action="selectedLead.phoneStatusUrl"
                                  class="rounded-xl bg-slate-50 p-4 md:col-span-2 dark:bg-zinc-950"
                                  x-on:submit="if ([...$el.querySelectorAll('select')].some((select) => select.value === 'DNC') && !confirm('DNC numbers will automatically move this lead to Archived Leads. Continue?')) { $event.preventDefault(); }">
                                @csrf
                                <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                                <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Phone Number Status</p>

                                <div class="mt-3 space-y-3">
                                    <template x-for="phoneNumber in selectedLead.phoneNumbers" :key="`status-${phoneNumber}`">
                                        <label class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_220px] md:items-center">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="phoneNumber"></span>
                                            <select x-bind:name="`phone_number_statuses[${phoneNumber}]`"
                                                    class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                                <option value="">No status</option>
                                                <option value="Verified" x-bind:selected="selectedLead.phoneNumberStatuses?.[phoneNumber] === 'Verified' || (selectedLead.verifiedPhoneNumbers || []).includes(phoneNumber)">Verified</option>
                                                <option value="No Answer" x-bind:selected="selectedLead.phoneNumberStatuses?.[phoneNumber] === 'No Answer'">No Answer</option>
                                                <option value="NIS" x-bind:selected="selectedLead.phoneNumberStatuses?.[phoneNumber] === 'NIS'">NIS - Not in Service</option>
                                                <option value="DNC" x-bind:selected="selectedLead.phoneNumberStatuses?.[phoneNumber] === 'DNC'">DNC - Do not call</option>
                                                <option value="Wrong Number" x-bind:selected="selectedLead.phoneNumberStatuses?.[phoneNumber] === 'Wrong Number'">Wrong Number</option>
                                                <option value="Voice Mail" x-bind:selected="selectedLead.phoneNumberStatuses?.[phoneNumber] === 'Voice Mail'">Voice Mail</option>
                                            </select>
                                        </label>
                                    </template>
                                </div>

                                <div class="mt-4 flex justify-end">
                                    <button type="submit"
                                            class="rounded-xl bg-zinc-950 px-4 py-2 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                        Save Phone Status
                                    </button>
                                </div>
                            </form>
                        @endif

                        <div class="rounded-xl bg-slate-50 p-4 md:col-span-2 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Return Notes</p>
                            <p class="mt-1 whitespace-pre-wrap text-sm font-semibold leading-6 text-slate-900 dark:text-zinc-100" x-text="selectedLead.returnNotes"></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
