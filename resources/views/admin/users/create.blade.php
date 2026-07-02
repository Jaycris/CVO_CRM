<x-app-layout>
    <x-slot name="header">
        Create User
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Create User</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Add an employee account, assign their role, and prepare their invitation.
                </p>
            </div>

            <a href="{{ route('admin.users.index') }}"
               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50">
                Back to Users
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" role="alert">
                <p class="font-semibold">Please check the form and try again.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 xl:col-span-2">
                <form method="POST"
                      action="{{ route('admin.users.store') }}"
                      class="space-y-5"
                      x-data="{
                          department: @js(old('department', '')),
                          brandId: @js((string) old('brand_id', $defaultBrandId)),
                          roleId: @js(old('role_id', '')),
                          changeDepartment() {
                              const selectedRole = this.$refs.roleSelect?.selectedOptions?.[0];
                              if (selectedRole && selectedRole.dataset.department !== this.department) {
                                  this.roleId = '';
                              }
                          }
                      }">
                    @csrf

                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="first_name" class="mb-2 block text-sm font-medium text-slate-700">
                                First Name <span class="text-rose-600">*</span>
                            </label>
                            <input id="first_name"
                                   name="first_name"
                                   type="text"
                                   value="{{ old('first_name') }}"
                                   required
                                   autofocus
                                   class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <div>
                            <label for="last_name" class="mb-2 block text-sm font-medium text-slate-700">
                                Last Name <span class="text-rose-600">*</span>
                            </label>
                            <input id="last_name"
                                   name="last_name"
                                   type="text"
                                   value="{{ old('last_name') }}"
                                   required
                                   class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <label for="department" class="mb-2 block text-sm font-medium text-slate-700">
                            Department <span class="text-rose-600">*</span>
                        </label>
                        <select id="department"
                                name="department"
                                x-model="department"
                                x-on:change="changeDepartment()"
                                required
                                class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Select a department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department }}" @selected(old('department') === $department)>
                                    {{ $department }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('department')" class="mt-2" />
                    </div>

                    <div>
                        <label for="brand_id" class="mb-2 block text-sm font-medium text-slate-700">
                            Brand / Account <span class="text-rose-600">*</span>
                        </label>
                        <select id="brand_id"
                                name="brand_id"
                                x-model="brandId"
                                required
                                class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected((string) old('brand_id', $defaultBrandId) === (string) $brand->id)>
                                    {{ $brand->imprint_name }}
                                    @if ($brand->imprint_name === 'CreatiVision Outsourcing')
                                        (Parent Account)
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-slate-500">
                            Users without a specific brand should stay under CreatiVision Outsourcing.
                        </p>
                        <x-input-error :messages="$errors->get('brand_id')" class="mt-2" />
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700">
                            Email Address <span class="text-rose-600">*</span>
                        </label>
                        <input id="email"
                               name="email"
                               type="email"
                               value="{{ old('email') }}"
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
                               value="{{ old('phone_number') }}"
                               placeholder="+1 700 000 0000"
                               x-bind:required="department === 'Sales'"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                        <p class="mt-2 text-xs text-slate-500" x-show="department && department !== 'Sales'" x-cloak>
                            Optional for non-Sales users.
                        </p>
                        <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                    </div>

                    <div x-show="department" x-cloak>
                        <label for="role_id" class="mb-2 block text-sm font-medium text-slate-700">
                            Role <span class="text-rose-600">*</span>
                        </label>
                        <select id="role_id"
                                name="role_id"
                                x-model="roleId"
                                x-ref="roleSelect"
                                required
                                class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
                            <option value="">Select a role</option>
                            @foreach ($roles as $role)
                                <option value="{{ $role->id }}"
                                        data-department="{{ $role->department }}"
                                        x-bind:hidden="department !== @js($role->department)"
                                        x-bind:disabled="department !== @js($role->department)"
                                        @selected((string) old('role_id') === (string) $role->id)>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('role_id')" class="mt-2" />
                    </div>

                    <div x-show="!department" x-cloak class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                        Select a department first to choose an available role.
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('admin.users.index') }}"
                           class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">
                            Cancel
                        </a>

                        <button type="submit"
                                class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2">
                            Create User
                        </button>
                    </div>
                </form>
            </div>

            <aside class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="text-base font-bold text-slate-900">Account Setup</h2>
                <div class="mt-5 space-y-4 text-sm text-slate-600">
                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="font-semibold text-slate-800">Temporary password</p>
                        <p class="mt-1">A secure temporary password is generated when the user is created.</p>
                    </div>

                    <div class="rounded-xl bg-amber-50 p-4">
                        <p class="font-semibold text-amber-800">Invitation status</p>
                        <p class="mt-1">The invitation expiry is set for 7 days. Email sending can be connected next.</p>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4">
                        <p class="font-semibold text-slate-800">Role access</p>
                        <p class="mt-1">The selected role controls where this user belongs in the CRM workflow.</p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</x-app-layout>
