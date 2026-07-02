<x-app-layout>
    <x-slot name="header">
        Trash
    </x-slot>

    @php
        $tabs = [
            'users' => 'Users',
            'leads' => 'Leads',
            'endorsements' => 'Sales Endorsements',
            'payments' => 'Payment Records',
            'projects' => 'Fulfillment Records',
        ];
    @endphp

    <div class="space-y-6"
         x-data="{ selectedIds: [] }"
         x-effect="selectedIds = []">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Trash / Deleted Records</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Restore soft-deleted records or permanently delete them when needed.
                </p>
            </div>

            <div class="flex flex-wrap rounded-xl border border-slate-200 bg-white p-1 text-sm shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
                @foreach ($tabs as $tabType => $tabLabel)
                    <a href="{{ route('admin.trash.index', ['type' => $tabType]) }}"
                       class="{{ $type === $tabType ? 'bg-zinc-950 text-amber-100 dark:bg-amber-400 dark:text-zinc-950' : 'text-slate-600 dark:text-zinc-300' }} rounded-lg px-3 py-2 font-semibold">
                        {{ $tabLabel }}
                        <span class="ml-1 text-xs opacity-75">{{ $counts[$tabType] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                Please select at least one record.
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">{{ $tabs[$type] }} Trash</h2>
                        <p x-show="selectedIds.length > 0"
                           x-cloak
                           x-text="`${selectedIds.length} selected`"
                           class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-200"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <form method="POST"
                              action="{{ route('admin.trash.restore') }}"
                              x-on:submit="if (selectedIds.length === 0) { $event.preventDefault(); }">
                            @csrf
                            <input type="hidden" name="type" value="{{ $type }}">
                            <template x-for="recordId in selectedIds" :key="`restore-${recordId}`">
                                <input type="hidden" name="record_ids[]" :value="recordId">
                            </template>
                            <button type="submit"
                                    x-bind:disabled="selectedIds.length === 0"
                                    x-bind:class="selectedIds.length > 0 ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Restore selected records"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 14 4 9m0 0 5-5M4 9h10a6 6 0 1 1 0 12h-1" />
                                </svg>
                            </button>
                        </form>

                        <form method="POST"
                              action="{{ route('admin.trash.force-delete') }}"
                              x-on:submit="if (selectedIds.length === 0 || !confirm('Permanently delete selected record(s)? This cannot be undone.')) { $event.preventDefault(); }">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="type" value="{{ $type }}">
                            <template x-for="recordId in selectedIds" :key="`force-delete-${recordId}`">
                                <input type="hidden" name="record_ids[]" :value="recordId">
                            </template>
                            <button type="submit"
                                    x-bind:disabled="selectedIds.length === 0"
                                    x-bind:class="selectedIds.length > 0 ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Permanently delete selected records"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </form>

                        <form method="GET" action="{{ route('admin.trash.index') }}" class="flex flex-wrap items-center gap-2">
                            <input type="hidden" name="type" value="{{ $type }}">
                            <input type="text"
                                   name="search"
                                   value="{{ $search }}"
                                   placeholder="Search deleted records..."
                                   class="w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">
                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Search
                            </button>
                            @if ($search !== '')
                                <a href="{{ route('admin.trash.index', ['type' => $type]) }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                        </form>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @php $visibleRecordIds = $records->pluck('id')->values(); @endphp
                <table class="w-full table-fixed text-left text-xs">
                    <thead class="bg-slate-50 text-[11px] uppercase leading-tight text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="w-[4%] px-3 py-4">
                                <input type="checkbox"
                                       class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950"
                                       x-bind:checked="@js($visibleRecordIds).length > 0 && @js($visibleRecordIds).every((id) => selectedIds.includes(id))"
                                       x-on:change="$event.target.checked ? selectedIds = Array.from(new Set([...selectedIds, ...@js($visibleRecordIds)])) : selectedIds = selectedIds.filter((id) => !@js($visibleRecordIds).includes(id))">
                            </th>
                            <th class="w-[22%] px-3 py-4">Primary</th>
                            <th class="w-[22%] px-3 py-4">Secondary</th>
                            <th class="w-[18%] px-3 py-4">Owner / Role</th>
                            <th class="w-[16%] px-3 py-4">Status / Amount</th>
                            <th class="w-[18%] px-3 py-4">Deleted</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($records as $record)
                            @php
                                $primary = match ($type) {
                                    'users' => trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: 'Unknown user',
                                    'leads' => $record->book_title ?: 'Untitled lead',
                                    'endorsements' => $record->book_title ?: 'Untitled endorsement',
                                    'payments' => $record->endorsement?->book_title ?: 'Payment record',
                                    'projects' => $record->endorsement?->book_title ?: 'Fulfillment record',
                                };
                                $secondary = match ($type) {
                                    'users' => $record->email,
                                    'leads' => $record->author_name,
                                    'endorsements' => $record->author_name,
                                    'payments' => $record->endorsement?->author_name ?: '-',
                                    'projects' => $record->endorsement?->author_name ?: '-',
                                };
                                $owner = match ($type) {
                                    'users' => $record->role?->name ?: $record->department ?: '-',
                                    'leads' => trim(($record->createdBy?->first_name ?? '') . ' ' . ($record->createdBy?->last_name ?? '')) ?: '-',
                                    'endorsements' => trim(($record->agent?->first_name ?? '') . ' ' . ($record->agent?->last_name ?? '')) ?: '-',
                                    'payments' => trim(($record->endorsement?->agent?->first_name ?? '') . ' ' . ($record->endorsement?->agent?->last_name ?? '')) ?: '-',
                                    'projects' => trim(($record->assignedUser?->first_name ?? '') . ' ' . ($record->assignedUser?->last_name ?? '')) ?: (trim(($record->fulfillmentOfficer?->first_name ?? '') . ' ' . ($record->fulfillmentOfficer?->last_name ?? '')) ?: '-'),
                                };
                                $status = match ($type) {
                                    'users' => $record->department ?: '-',
                                    'leads' => $record->verify_score ? "{$record->verify_score}/100" : 'Not verified',
                                    'endorsements' => '$' . number_format((float) $record->amount, 2),
                                    'payments' => $record->status ?: '-',
                                    'projects' => str($record->status ?: '-')->replace('_', ' ')->title(),
                                };
                            @endphp
                            <tr x-on:click="selectedIds.includes({{ $record->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $record->id }}) : selectedIds.push({{ $record->id }})"
                                x-bind:class="selectedIds.includes({{ $record->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                class="cursor-pointer align-top">
                                <td class="px-3 py-4">
                                    <input type="checkbox"
                                           x-bind:checked="selectedIds.includes({{ $record->id }})"
                                           x-on:click.stop="selectedIds.includes({{ $record->id }}) ? selectedIds = selectedIds.filter((id) => id !== {{ $record->id }}) : selectedIds.push({{ $record->id }})"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="break-words px-3 py-4 font-semibold leading-snug text-slate-900 dark:text-zinc-100">{{ $primary }}</td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $secondary ?: '-' }}</td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $owner }}</td>
                                <td class="break-words px-3 py-4 leading-snug text-slate-700 dark:text-zinc-300">{{ $status }}</td>
                                <td class="px-3 py-4 leading-snug text-slate-500 dark:text-zinc-400">{{ $record->deleted_at?->format('M d, Y h:i A') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-16 text-center text-sm text-slate-500 dark:text-zinc-400">
                                    No deleted {{ strtolower($tabs[$type]) }} found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
