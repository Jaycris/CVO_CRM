<x-app-layout>
    <x-slot name="header">
        Roles & Permissions
    </x-slot>

    <div class="space-y-6"
         x-data="{
            departmentModalOpen: false,
            editDepartmentModalOpen: null,
            createRoleModalOpen: false,
            editRoleModalOpen: null,
            createRoleStep: 1,
            createSelectedPermissions: [],
            editRoleStep: {},
            selectedRoleIds: [],
            selectedRoleId: null,
            openCreateRole() {
                this.createRoleStep = 1;
                this.createSelectedPermissions = [];
                this.createRoleModalOpen = true;
            },
            openEditRole(roleId) {
                this.editRoleStep[roleId] = 1;
                this.editRoleModalOpen = roleId;
            },
            toggleRole(roleId) {
                if (this.selectedRoleIds.includes(roleId)) {
                    this.selectedRoleIds = [];
                    this.selectedRoleId = null;
                    return;
                }

                this.selectedRoleIds = [roleId];
                this.selectedRoleId = roleId;
            },
            hasSelectedRole() {
                return this.selectedRoleIds.length === 1;
            }
         }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Roles & Permissions</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Manage departments, roles, and what each role can access or do.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="button"
                        x-on:click="departmentModalOpen = true"
                        class="rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                    + Add Department
                </button>

                <button type="button"
                        x-on:click="openCreateRole()"
                        class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                    + Create Role
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                <p>Please check the form and try again.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-xs font-normal">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[360px_1fr]">
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                    <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Departments</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                        Departments available for users and roles.
                    </p>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @forelse ($departments as $department)
                        <div class="flex items-center justify-between gap-3 px-6 py-4">
                            <div>
                                <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $department->name }}</p>
                                <p class="text-xs text-slate-500 dark:text-zinc-400">
                                    {{ $departmentRoleCounts[$department->name] ?? 0 }} role(s)
                                </p>
                            </div>
                            <button type="button"
                                    x-on:click="editDepartmentModalOpen = {{ $department->id }}"
                                    title="Edit department"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-amber-200 bg-amber-50 text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                </svg>
                            </button>
                        </div>
                    @empty
                        <div class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">
                            No departments yet.
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="border-b border-slate-200 px-5 py-3 dark:border-zinc-800">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Roles</h2>
                            <p class="mt-1 text-xs text-slate-500 dark:text-zinc-400">
                                Roles that have been created in the system.
                            </p>
                            <p x-show="selectedRoleIds.length > 0"
                               x-cloak
                               x-text="`${selectedRoleIds.length} selected`"
                               class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-200"></p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <button type="button"
                                    x-on:click="if (hasSelectedRole()) openEditRole(selectedRoleId)"
                                    x-bind:disabled="!hasSelectedRole()"
                                    x-bind:class="hasSelectedRole() ? 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200 dark:hover:bg-amber-400/20' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 dark:border-zinc-800 dark:bg-zinc-800 dark:text-zinc-500'"
                                    title="Edit selected role"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 7.125 16.875 4.5" />
                                </svg>
                            </button>

                            <form method="GET" action="{{ route('admin.roles-permissions.index') }}" class="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <input type="text"
                                   name="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search roles..."
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500 sm:w-56">
                            <select name="department"
                                    class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 sm:w-52">
                                <option value="">All departments</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->name }}" @selected(request('department') === $department->name)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-4 py-2.5 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Filter
                            </button>
                            @if (request('search') || request('department'))
                                <a href="{{ route('admin.roles-permissions.index') }}"
                                   class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                    Clear
                                </a>
                            @endif
                            </form>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full table-fixed text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950 dark:text-zinc-400">
                            <tr>
                                <th class="w-[6%] px-5 py-3"></th>
                                <th class="w-[42%] px-5 py-3">Role</th>
                                <th class="w-[24%] px-5 py-3">Department</th>
                                <th class="w-[28%] px-5 py-3">Permissions</th>
                            </tr>
                        </thead>

                        <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                            @forelse ($roles as $role)
                                <tr x-on:click="toggleRole({{ $role->id }})"
                                    x-bind:class="selectedRoleIds.includes({{ $role->id }}) ? 'bg-amber-50 dark:bg-amber-400/10' : 'hover:bg-slate-50 dark:hover:bg-zinc-800/60'"
                                    class="cursor-pointer">
                                    <td class="px-5 py-3">
                                        <input type="checkbox"
                                               x-bind:checked="selectedRoleIds.includes({{ $role->id }})"
                                               x-on:click.stop="toggleRole({{ $role->id }})"
                                               class="rounded border-slate-300 text-amber-600 shadow-sm focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950">
                                    </td>
                                    <td class="px-5 py-3">
                                        <p class="font-semibold text-slate-900 dark:text-zinc-100">{{ $role->name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-zinc-400" title="{{ $role->description ?: 'No description' }}">
                                            {{ $role->description ?: 'No description' }}
                                        </p>
                                    </td>
                                    <td class="truncate px-5 py-3 text-slate-700 dark:text-zinc-300">
                                        {{ $role->department ?: 'No department' }}
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 dark:bg-zinc-800 dark:text-zinc-200">
                                            {{ $role->permissionRecords->count() }} enabled
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-zinc-400">
                                        No roles match your filters.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($roles->hasPages())
                    <div class="border-t border-slate-200 px-5 py-3 dark:border-zinc-800">
                        {{ $roles->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div x-show="departmentModalOpen"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
             x-on:keydown.escape.window="departmentModalOpen = false">
            <div x-on:click.outside="departmentModalOpen = false"
                 class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Add Department</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Create a department for users and roles.</p>
                    </div>
                    <button type="button"
                            x-on:click="departmentModalOpen = false"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('admin.roles-permissions.departments.store') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <label for="department_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Department Name <span class="text-rose-600">*</span>
                        </label>
                        <input id="department_name"
                               name="department_name"
                               type="text"
                               value="{{ old('department_name') }}"
                               required
                               class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('department_name')" class="mt-2" />
                    </div>

                    <div class="flex justify-end gap-3">
                        <button type="button"
                                x-on:click="departmentModalOpen = false"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            Cancel
                        </button>
                        <button type="submit"
                                class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Create
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($departments as $department)
            <div x-show="editDepartmentModalOpen === {{ $department->id }}"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
                 x-on:keydown.escape.window="editDepartmentModalOpen = null">
                <div x-on:click.outside="editDepartmentModalOpen = null"
                     class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Edit Department</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Update this department name.</p>
                        </div>
                        <button type="button"
                                x-on:click="editDepartmentModalOpen = null"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form method="POST" action="{{ route('admin.roles-permissions.departments.update', $department) }}" class="mt-6 space-y-5">
                        @csrf
                        @method('PUT')
                        <div>
                            <label for="edit_department_name_{{ $department->id }}" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Department Name <span class="text-rose-600">*</span>
                            </label>
                            <input id="edit_department_name_{{ $department->id }}"
                                   name="department_name"
                                   type="text"
                                   value="{{ old('department_name', $department->name) }}"
                                   required
                                   class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <x-input-error :messages="$errors->get('department_name')" class="mt-2" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <button type="button"
                                    x-on:click="editDepartmentModalOpen = null"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Cancel
                            </button>
                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Save Department
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach

        <div x-show="createRoleModalOpen"
             x-cloak
             x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
             x-on:keydown.escape.window="createRoleModalOpen = false">
            <div x-on:click.outside="createRoleModalOpen = false"
                 class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Create Role</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            <span x-text="createRoleStep === 1 ? 'Step 1 of 2: Role details.' : 'Step 2 of 2: Permissions.'"></span>
                        </p>
                    </div>
                    <button type="button"
                            x-on:click="createRoleModalOpen = false"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form id="create-role-form"
                      method="POST"
                      action="{{ route('admin.roles-permissions.roles.store') }}"
                      class="mt-6">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">
                    <div x-show="createRoleStep === 1" x-cloak class="space-y-4">
                        @include('admin.roles-permissions.partials.role-details-fields', [
                            'departments' => $departments,
                            'role' => null,
                            'prefix' => 'create',
                        ])
                    </div>

                    <div x-show="createRoleStep === 2" x-cloak>
                        @include('admin.roles-permissions.partials.permission-fields', [
                            'availablePermissions' => $availablePermissions,
                            'selectedPermissions' => old('permissions', []),
                            'disabled' => false,
                            'xModel' => 'createSelectedPermissions',
                        ])
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <button type="button"
                                x-show="createRoleStep === 2"
                                x-on:click="createRoleStep = 1"
                                class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                            Back
                        </button>
                        <button type="button"
                                x-show="createRoleStep === 1"
                                x-on:click="createRoleStep = 2"
                                class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Next
                        </button>
                        <button type="submit"
                                x-show="createRoleStep === 2"
                                class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Create Role
                        </button>
                    </div>
                </form>
            </div>
        </div>

        @foreach ($roles as $role)
            <div x-show="editRoleModalOpen === {{ $role->id }}"
                 x-cloak
                 x-transition.opacity
                 class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/50 px-4"
                 x-on:keydown.escape.window="editRoleModalOpen = null">
                <div x-on:click.outside="editRoleModalOpen = null"
                     class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-6 shadow-xl dark:bg-zinc-900">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Edit Role</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                <span x-text="(editRoleStep[{{ $role->id }}] || 1) === 1 ? 'Step 1 of 2: Role details.' : 'Step 2 of 2: Permissions.'"></span>
                            </p>
                        </div>
                        <button type="button"
                                x-on:click="editRoleModalOpen = null"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100 dark:text-zinc-400 dark:hover:bg-zinc-800">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <form id="edit-role-form-{{ $role->id }}"
                          method="POST"
                          action="{{ route('admin.roles-permissions.roles.update', $role) }}"
                          x-data="{ selectedPermissions: @js($role->permissionRecords->pluck('key')->values()->all()) }"
                          class="mt-6">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="return_to" value="{{ request()->fullUrl() }}">

                        <div x-show="(editRoleStep[{{ $role->id }}] || 1) === 1" x-cloak class="space-y-4">
                            @include('admin.roles-permissions.partials.role-details-fields', [
                                'departments' => $departments,
                                'role' => $role,
                                'prefix' => 'edit_' . $role->id,
                            ])
                        </div>

                        <div x-show="(editRoleStep[{{ $role->id }}] || 1) === 2" x-cloak>
                            @include('admin.roles-permissions.partials.permission-fields', [
                                'availablePermissions' => $availablePermissions,
                                'selectedPermissions' => $role->permissionRecords->pluck('key')->all(),
                                'disabled' => $role->name === 'Admin',
                                'xModel' => 'selectedPermissions',
                            ])
                            @if ($role->name === 'Admin')
                                <p class="mt-3 text-xs text-slate-500 dark:text-zinc-400">Admin keeps all permissions enabled.</p>
                            @endif
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <button type="button"
                                    x-show="(editRoleStep[{{ $role->id }}] || 1) === 2"
                                    x-on:click="editRoleStep[{{ $role->id }}] = 1"
                                    class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Back
                            </button>
                            <button type="button"
                                    x-show="(editRoleStep[{{ $role->id }}] || 1) === 1"
                                    x-on:click="editRoleStep[{{ $role->id }}] = 2"
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Next
                            </button>
                            <button type="submit"
                                    x-show="(editRoleStep[{{ $role->id }}] || 1) === 2"
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                                Save Role
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
