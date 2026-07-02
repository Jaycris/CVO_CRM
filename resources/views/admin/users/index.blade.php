<x-app-layout>
    <x-slot name="header">
        Users
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Users</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Manage system users, roles, invitations, and account status.
                </p>
            </div>

            <a href="{{ route('admin.users.create') }}"
               class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                + Create User
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">
                {{ session('error') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800"
             x-data="{
                selectedIds: [],
                selectedUser: {},
                toggleUser(user) {
                    if (this.selectedIds.includes(user.id)) {
                        this.selectedIds = this.selectedIds.filter((id) => id !== user.id);
                        this.selectedUser = {};
                        return;
                    }

                    this.selectedIds = [user.id];
                    this.selectedUser = user;
                },
                hasSingleSelection() {
                    return this.selectedIds.length === 1;
                },
                toggleVisibleUsers(users) {
                    const visibleIds = users.map((user) => user.id);
                    const allVisibleSelected = visibleIds.length > 0 && visibleIds.every((id) => this.selectedIds.includes(id));

                    if (allVisibleSelected) {
                        this.selectedIds = this.selectedIds.filter((id) => !visibleIds.includes(id));
                        this.selectedUser = {};
                        return;
                    }

                    this.selectedIds = Array.from(new Set([...this.selectedIds, ...visibleIds]));
                    this.selectedUser = this.selectedIds.length === 1 ? users.find((user) => user.id === this.selectedIds[0]) || {} : {};
                },
                allVisibleUsersSelected(users) {
                    const visibleIds = users.map((user) => user.id);

                    return visibleIds.length > 0 && visibleIds.every((id) => this.selectedIds.includes(id));
                }
             }">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">User Directory</h2>
                        <p x-show="selectedIds.length > 0"
                           x-cloak
                           x-text="`${selectedIds.length} selected`"
                           class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-200"></p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a x-bind:href="hasSingleSelection() ? selectedUser.viewUrl : '#'"
                           x-bind:class="hasSingleSelection() ? 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                           x-on:click="if (!hasSingleSelection()) { $event.preventDefault(); }"
                           title="View selected user"
                           class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12s3.75-6.75 9.75-6.75S21.75 12 21.75 12 18 18.75 12 18.75 2.25 12 2.25 12Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                        </a>

                        <a x-bind:href="hasSingleSelection() ? selectedUser.editUrl : '#'"
                           x-bind:class="hasSingleSelection() ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                           x-on:click="if (!hasSingleSelection()) { $event.preventDefault(); }"
                           title="Edit selected user"
                           class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                            </svg>
                        </a>

                        <form method="POST"
                              x-bind:action="selectedUser.suspendUrl"
                              x-on:submit="if (!hasSingleSelection() || !selectedUser.canSuspend || !confirm('Suspend this user? Active assigned leads will move back to Unassigned Leads.')) { $event.preventDefault(); }">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    x-bind:disabled="!hasSingleSelection() || !selectedUser.canSuspend"
                                    x-bind:class="hasSingleSelection() && selectedUser.canSuspend ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Suspend selected user"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 0 0 5.636 5.636m12.728 12.728A9 9 0 0 1 5.636 5.636m12.728 12.728L5.636 5.636" />
                                </svg>
                            </button>
                        </form>

                        <form method="POST"
                              x-bind:action="selectedUser.unsuspendUrl"
                              x-on:submit="if (!hasSingleSelection() || !selectedUser.isSuspended || !confirm('Reactivate this user?')) { $event.preventDefault(); }">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    x-bind:disabled="!hasSingleSelection() || !selectedUser.isSuspended"
                                    x-bind:class="hasSingleSelection() && selectedUser.isSuspended ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200 dark:hover:bg-emerald-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Reactivate selected user"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </button>
                        </form>

                        <form method="POST"
                              x-bind:action="selectedUser.deleteUrl"
                              x-on:submit="if (!hasSingleSelection() || !selectedUser.canDelete || !confirm('Delete this user? This action cannot be undone.')) { $event.preventDefault(); }">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    x-bind:disabled="!hasSingleSelection() || !selectedUser.canDelete"
                                    x-bind:class="hasSingleSelection() && selectedUser.canDelete ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200 dark:hover:bg-rose-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Delete selected user"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673A2.25 2.25 0 0 1 15.916 21H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </form>

                        <input type="text"
                               placeholder="Search users..."
                               class="w-72 rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                @php
                    $visibleUsers = $users->map(fn ($user) => [
                        'id' => $user->id,
                        'viewUrl' => route('admin.users.show', $user),
                        'editUrl' => route('admin.users.edit', $user),
                        'suspendUrl' => route('admin.users.suspend', $user),
                        'unsuspendUrl' => route('admin.users.unsuspend', $user),
                        'deleteUrl' => route('admin.users.destroy', $user),
                        'canDelete' => $user->id !== 1,
                        'canSuspend' => $user->id !== 1 && $user->id !== auth()->id() && ! $user->suspended_at,
                        'isSuspended' => ! is_null($user->suspended_at),
                    ])->values();
                @endphp
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                        <tr>
                            <th class="w-12 px-6 py-4">
                                <input type="checkbox"
                                       class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950"
                                       x-bind:checked="allVisibleUsersSelected(@js($visibleUsers))"
                                       x-on:change="toggleVisibleUsers(@js($visibleUsers))">
                            </th>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Role</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Email Status</th>
                            <th class="px-6 py-4">Password Status</th>
                            <th class="px-6 py-4">Invitation</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($users as $user)
                            @php
                                $userPayload = [
                                    'id' => $user->id,
                                    'viewUrl' => route('admin.users.show', $user),
                                    'editUrl' => route('admin.users.edit', $user),
                                    'suspendUrl' => route('admin.users.suspend', $user),
                                    'unsuspendUrl' => route('admin.users.unsuspend', $user),
                                    'deleteUrl' => route('admin.users.destroy', $user),
                                    'canDelete' => $user->id !== 1,
                                    'canSuspend' => $user->id !== 1 && $user->id !== auth()->id() && ! $user->suspended_at,
                                    'isSuspended' => ! is_null($user->suspended_at),
                                ];
                            @endphp
                            <tr x-on:click="toggleUser(@js($userPayload))"
                                x-bind:class="selectedIds.includes({{ $user->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                class="cursor-pointer">
                                <td class="px-6 py-4">
                                    <input type="checkbox"
                                           x-bind:checked="selectedIds.includes({{ $user->id }})"
                                           x-on:click.stop="toggleUser(@js($userPayload))"
                                           class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-100 font-bold text-amber-800 dark:bg-amber-400/15 dark:text-amber-200">
                                            {{ strtoupper(substr($user->first_name, 0, 1)) }}
                                        </div>

                                        <div>
                                            <p class="font-semibold text-slate-900 dark:text-zinc-100">
                                                {{ $user->first_name }} {{ $user->last_name }}
                                            </p>
                                            <p class="text-xs text-slate-500 dark:text-zinc-400">
                                                {{ $user->department ?? 'No department' }}
                                            </p>
                                            <p class="text-xs text-slate-400 dark:text-zinc-500">
                                                {{ $user->phone_number ?? 'No phone number' }}
                                            </p>
                                            <p class="text-xs text-slate-400 dark:text-zinc-500">
                                                {{ $user->team?->name ? 'Team: ' . $user->team->name : 'No team' }}
                                            </p>
                                        </div>
                                    </div>
                                </td>

                                <td class="px-6 py-4">
                                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">
                                        {{ $user->role->name ?? 'No Role' }}
                                    </span>
                                </td>

                                <td class="px-6 py-4">
                                    @if ($user->suspended_at)
                                        <div class="space-y-1">
                                            <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                                Suspended
                                            </span>
                                            <p class="text-xs text-slate-500 dark:text-zinc-400">
                                                {{ $user->suspended_at->format('M d, Y') }}
                                            </p>
                                        </div>
                                    @else
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            Active
                                        </span>
                                    @endif

                                    @if (($user->active_assigned_leads_count ?? 0) > 0)
                                        <p class="mt-2 text-xs text-slate-500 dark:text-zinc-400">
                                            {{ $user->active_assigned_leads_count }} assigned lead(s)
                                        </p>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    @if ($user->email_verified_at)
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            Verified
                                        </span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700">
                                            Pending
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4">
                                    @if ($user->password_created_at)
                                        <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                            Created
                                        </span>
                                    @else
                                        <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                            Not Created
                                        </span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 text-slate-600 dark:text-zinc-300">
                                    @if ($user->invitation_expires_at)
                                        {{ $user->invitation_expires_at->format('M d, Y') }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>

                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-500 dark:text-zinc-400">
                                    No users found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                    {{ $users->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
