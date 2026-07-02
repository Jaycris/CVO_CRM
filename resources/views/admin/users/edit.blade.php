<x-app-layout>
    <x-slot name="header">
        Edit User
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Edit User</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Update user details and role assignment.
                </p>
            </div>

            <a href="{{ route('admin.users.index') }}"
               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Back to Users
            </a>
        </div>

        @php
            $rolePermissionKeys = $user->role?->permissionRecords->pluck('key')->values()->all() ?? [];
            $allowOverrideKeys = $user->permissionOverrides->where('pivot.effect', 'allow')->pluck('key')->values()->all();
            $denyOverrideKeys = $user->permissionOverrides->where('pivot.effect', 'deny')->pluck('key')->values()->all();
            $effectivePermissionKeys = collect($rolePermissionKeys)
                ->merge($allowOverrideKeys)
                ->diff($denyOverrideKeys)
                ->values()
                ->all();
        @endphp

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <form method="POST"
                  action="{{ route('admin.users.update', $user) }}"
                  class="space-y-5"
                  x-data="{
                      department: @js(old('department', $user->department ?? '')),
                      brandId: @js((string) old('brand_id', $user->brand_id ?? $defaultBrandId)),
                      roleId: @js((string) old('role_id', $user->role_id)),
                      selectedPermissions: @js(old('final_permissions', $effectivePermissionKeys)),
                      rolesByDepartment: @js($roles->groupBy('department')->map(fn ($departmentRoles) => $departmentRoles->map(fn ($role) => ['id' => (string) $role->id, 'name' => $role->name])->values())),
                      rolePermissionsById: @js($roles->mapWithKeys(fn ($role) => [(string) $role->id => $role->permissionRecords->pluck('key')->values()->all()])),
                      roles() {
                          return this.rolesByDepartment[this.department] || [];
                      },
                      rolePermissions() {
                          return this.rolePermissionsById[this.roleId] || [];
                      },
                      resetPermissionsToRole() {
                          this.selectedPermissions = [...this.rolePermissions()];
                      },
                      permissionSource(permission) {
                          if (this.rolePermissions().includes(permission)) {
                              return 'Role default';
                          }

                          if (this.selectedPermissions.includes(permission)) {
                              return 'User extra';
                          }

                          return 'Not enabled';
                      }
                  }">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="first_name" class="mb-2 block text-sm font-medium text-slate-700">First Name <span class="text-rose-600">*</span></label>
                        <input id="first_name"
                               name="first_name"
                               type="text"
                               value="{{ old('first_name', $user->first_name) }}"
                               required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="last_name" class="mb-2 block text-sm font-medium text-slate-700">Last Name <span class="text-rose-600">*</span></label>
                        <input id="last_name"
                               name="last_name"
                               type="text"
                               value="{{ old('last_name', $user->last_name) }}"
                               required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label for="department" class="mb-2 block text-sm font-medium text-slate-700">Department <span class="text-rose-600">*</span></label>
                    <select id="department"
                            name="department"
                            x-model="department"
                            x-on:change="roleId = ''; selectedPermissions = []"
                            required
                            class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Select a department</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department }}" @selected(old('department', $user->department) === $department)>
                                {{ $department }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('department')" class="mt-2" />
                </div>

                <div>
                    <label for="brand_id" class="mb-2 block text-sm font-medium text-slate-700">Brand / Account <span class="text-rose-600">*</span></label>
                    <select id="brand_id"
                            name="brand_id"
                            x-model="brandId"
                            required
                            class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        @foreach ($brands as $brand)
                            <option value="{{ $brand->id }}" @selected((string) old('brand_id', $user->brand_id ?? $defaultBrandId) === (string) $brand->id)>
                                {{ $brand->imprint_name }}
                                @if ($brand->imprint_name === 'CreatiVision Outsourcing')
                                    (Parent Account)
                                @endif
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Users without a specific brand should stay under CreatiVision Outsourcing.</p>
                    <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                </div>

                <div>
                    <label for="email" class="mb-2 block text-sm font-medium text-slate-700">Email Address <span class="text-rose-600">*</span></label>
                    <input id="email"
                           name="email"
                           type="email"
                           value="{{ old('email', $user->email) }}"
                           required
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div>
                    <label for="phone_number" class="mb-2 block text-sm font-medium text-slate-700">
                        Phone Number <span x-show="department === 'Sales'" class="text-rose-600">*</span>
                    </label>
                    <input id="phone_number"
                           name="phone_number"
                           type="tel"
                           value="{{ old('phone_number', $user->phone_number) }}"
                           placeholder="+63 900 000 0000"
                           x-bind:required="department === 'Sales'"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                    <p class="mt-2 text-xs text-slate-500" x-show="department && department !== 'Sales'" x-cloak>
                        Optional for non-Sales users.
                    </p>
                    <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                </div>

                <div x-show="department" x-cloak>
                    <label for="role_id" class="mb-2 block text-sm font-medium text-slate-700">Role <span class="text-rose-600">*</span></label>
                    <select id="role_id"
                            name="role_id"
                            x-model="roleId"
                            x-on:change="resetPermissionsToRole()"
                            required
                            class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <option value="">Select a role</option>
                        @foreach ($roles as $role)
                            <option value="{{ $role->id }}"
                                    data-department="{{ $role->department }}"
                                    x-show="$el.dataset.department === department"
                                    @selected((string) old('role_id', $user->role_id) === (string) $role->id)>
                                {{ $role->name }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                </div>

                <div x-show="!department" x-cloak class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                    Select a department first to choose an available role.
                </div>

                <div class="rounded-2xl border border-slate-200 p-5">
                    <div>
                        <h2 class="text-base font-bold text-slate-900">User Permissions</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            Checked permissions are the access this user will have. Role default permissions are already checked.
                        </p>
                    </div>

                    <template x-for="permissionKey in selectedPermissions" :key="permissionKey">
                        <input type="hidden" name="final_permissions[]" :value="permissionKey">
                    </template>

                    <div class="mt-5 max-h-[36rem] space-y-5 overflow-y-auto pr-2">
                        @foreach (collect($availablePermissions)->groupBy('group', true) as $groupName => $permissions)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase text-slate-600">{{ $groupName }}</p>
                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    @foreach ($permissions as $permissionKey => $permission)
                                        <label class="flex gap-3 rounded-lg bg-white p-3 shadow-sm ring-1 ring-slate-200">
                                            <input type="checkbox"
                                                   value="{{ $permissionKey }}"
                                                   x-model="selectedPermissions"
                                                   class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                                            <span>
                                                <span class="flex flex-wrap items-center gap-2">
                                                    <span class="block text-sm font-semibold text-slate-900">{{ $permission['label'] }}</span>
                                                    <span x-show="permissionSource('{{ $permissionKey }}') === 'Role default'"
                                                          x-cloak
                                                          class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold uppercase text-slate-500">
                                                        Role
                                                    </span>
                                                    <span x-show="permissionSource('{{ $permissionKey }}') === 'User extra'"
                                                          x-cloak
                                                          class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-700">
                                                        Extra
                                                    </span>
                                                </span>
                                                <span class="block text-xs leading-5 text-slate-500">{{ $permission['description'] }}</span>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button"
                                x-on:click="resetPermissionsToRole()"
                                class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                            Reset to Role Default
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.users.index') }}"
                       class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                        Cancel
                    </a>

                    <button type="submit"
                            class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
