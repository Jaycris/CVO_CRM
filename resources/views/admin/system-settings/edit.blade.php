<x-app-layout>
    <x-slot name="header">
        System Settings
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">System Settings</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Control global CRM display settings used across all departments.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        <div class="max-w-2xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <form method="POST" action="{{ route('admin.system-settings.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="records_per_page" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Listed Records Per Page <span class="text-rose-600">*</span>
                    </label>
                    <select id="records_per_page"
                            name="records_per_page"
                            required
                            class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach ($recordsPerPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) old('records_per_page', $recordsPerPage) === $option)>
                                {{ $option }} records
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        This controls paginated directory tables across Leads, Sales, Finance, Production, Reports, and Admin pages.
                    </p>
                    <x-input-error :messages="$errors->get('records_per_page')" class="mt-2" />
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    Higher values show more rows before pagination, but very large pages can make the CRM slower. The maximum is 100 records per page.
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-xl bg-[var(--brand-primary)] px-5 py-3 text-sm font-semibold text-white shadow-sm hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)] focus:ring-offset-2 dark:ring-offset-zinc-900">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
