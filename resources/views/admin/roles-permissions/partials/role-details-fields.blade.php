<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div>
        <label for="{{ $prefix }}_role_department" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
            Department <span class="text-rose-600">*</span>
        </label>
        <select id="{{ $prefix }}_role_department"
                name="department"
                required
                class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
            <option value="">Select a department</option>
            @foreach ($departments as $department)
                <option value="{{ $department->name }}" @selected(old('department', $role?->department) === $department->name)>
                    {{ $department->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('department')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $prefix }}_role_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
            Role Name <span class="text-rose-600">*</span>
        </label>
        <input id="{{ $prefix }}_role_name"
               name="role_name"
               type="text"
               value="{{ old('role_name', $role?->name) }}"
               required
               class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
        <x-input-error :messages="$errors->get('role_name')" class="mt-2" />
    </div>
</div>

<div>
    <label for="{{ $prefix }}_role_description" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
        Description
    </label>
    <textarea id="{{ $prefix }}_role_description"
              name="description"
              rows="4"
              class="w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('description', $role?->description) }}</textarea>
    <x-input-error :messages="$errors->get('description')" class="mt-2" />
</div>
