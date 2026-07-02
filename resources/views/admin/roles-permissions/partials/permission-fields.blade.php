@php
    $selectedPermissionKeys = collect($selectedPermissions ?? [])
        ->map(fn ($permission) => (string) $permission)
        ->values()
        ->all();
@endphp

<div>
    <p class="text-sm font-semibold text-slate-900 dark:text-zinc-100">Permissions</p>
    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
        Select what this role can access and what actions it can do.
    </p>

    @isset($xModel)
        <template x-for="permissionKey in {{ $xModel }}" :key="permissionKey">
            <input type="hidden" name="permission_flags[]" :value="permissionKey">
        </template>
    @endisset

    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach (collect($availablePermissions)->groupBy('group', true) as $groupName => $permissions)
            <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                <h4 class="text-sm font-semibold text-slate-900 dark:text-zinc-100">{{ $groupName }}</h4>

                <div class="mt-3 space-y-3">
                    @foreach ($permissions as $permissionKey => $permission)
                        <label class="flex gap-3">
                            <input type="checkbox"
                                   value="{{ $permissionKey }}"
                                   @isset($xModel)
                                       x-model="{{ $xModel }}"
                                   @else
                                       name="permission_flags[]"
                                   @endisset
                                   @checked(in_array($permissionKey, $selectedPermissionKeys, true))
                                   @disabled($disabled)
                                   class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500 disabled:cursor-not-allowed disabled:opacity-60">
                            <span>
                                <span class="block text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ $permission['label'] }}</span>
                                <span class="block text-xs leading-5 text-slate-500 dark:text-zinc-400">{{ $permission['description'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    <x-input-error :messages="$errors->get('permissions')" class="mt-2" />
</div>
