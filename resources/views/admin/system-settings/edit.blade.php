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

        <div class="max-w-3xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <form method="POST" action="{{ route('admin.system-settings.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <label for="records_per_page" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        General Records Per Page <span class="text-rose-600">*</span>
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
                        This controls Users, Admin, Finance, Production, Reports, and other general CRM tables.
                    </p>
                    <x-input-error :messages="$errors->get('records_per_page')" class="mt-2" />
                </div>

                <div>
                    <label for="leads_sales_records_per_page" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Leads & Sales Records Per Page <span class="text-rose-600">*</span>
                    </label>
                    <select id="leads_sales_records_per_page"
                            name="leads_sales_records_per_page"
                            required
                            class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        @foreach ($recordsPerPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) old('leads_sales_records_per_page', $leadsSalesRecordsPerPage) === $option)>
                                {{ $option }} records
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        This controls lead lists and Sales pages such as New Leads, Assigned Leads, Pipeline, Prospect, Scheduled Callback, Sold, Sales Endorsements, and Sales Activity.
                    </p>
                    <x-input-error :messages="$errors->get('leads_sales_records_per_page')" class="mt-2" />
                </div>

                <div class="border-t border-slate-200 pt-5 dark:border-zinc-800">
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Integration Employee Lookup</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                        These settings let CRM search employees from the connected system when creating users.
                    </p>
                </div>

                <div>
                    <label for="hris_base_url" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Integration API Base URL
                    </label>
                    <input id="hris_base_url"
                           name="hris_base_url"
                           type="url"
                           value="{{ old('hris_base_url', $hrisBaseUrl) }}"
                           placeholder="http://127.0.0.1:8001"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        Use the connected app URL only, without /api/crm at the end.
                    </p>
                    <x-input-error :messages="$errors->get('hris_base_url')" class="mt-2" />
                </div>

                <div>
                    <label for="hris_crm_lookup_token" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Integration Lookup Token
                    </label>
                    <input id="hris_crm_lookup_token"
                           name="hris_crm_lookup_token"
                           type="text"
                           value="{{ old('hris_crm_lookup_token', $hrisCrmLookupToken) }}"
                           placeholder="Paste the integration token for CRM lookup"
                           class="w-full rounded-xl border-slate-300 px-4 py-3 font-mono text-xs shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                        This is the token generated in the connected system for CRM employee lookup.
                    </p>
                    <x-input-error :messages="$errors->get('hris_crm_lookup_token')" class="mt-2" />
                </div>

                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    The integration lookup uses /api/crm/health, /api/crm/employees, and /api/crm/employees/{id} automatically.
                </div>

                <div class="flex justify-start sm:justify-end">
                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:ring-offset-zinc-900"
                            style="background-color: #065f46; color: #ffffff;">
                        Save Settings
                    </button>
                </div>
            </form>
        </div>

        <div class="max-w-3xl rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Integration Commission API</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                        Paste these URLs and token in PHREMS so it can mirror commission setup and request commission slips from the CRM.
                    </p>
                </div>

                <form method="POST" action="{{ route('admin.system-settings.hris-api-token.regenerate') }}"
                      class="shrink-0"
                      onsubmit="return confirm('Generate a new integration API token? The old token will stop working after this.');">
                    @csrf
                    <button type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 sm:w-auto dark:ring-offset-zinc-900"
                            style="background-color: #065f46; color: #ffffff;">
                        Generate Token
                    </button>
                </form>
            </div>

            <div class="mt-5">
                <label for="commission_slip_api_url" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                    Commission Slip API URL
                </label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="commission_slip_api_url"
                           type="text"
                           readonly
                           value="{{ $commissionSlipApiUrl }}"
                           class="min-w-0 flex-1 rounded-xl border-slate-300 px-4 py-3 font-mono text-xs shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('commission_slip_api_url').value); this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500);"
                            class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)] focus:ring-offset-2 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:ring-offset-zinc-900">
                        Copy
                    </button>
                </div>
            </div>

            <div class="mt-5">
                <label for="sales_performance_mtd_api_url" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                    Sales Performance MTD API URL
                </label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="sales_performance_mtd_api_url"
                           type="text"
                           readonly
                           value="{{ $salesPerformanceMtdApiUrl }}"
                           class="min-w-0 flex-1 rounded-xl border-slate-300 px-4 py-3 font-mono text-xs shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('sales_performance_mtd_api_url').value); this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500);"
                            class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)] focus:ring-offset-2 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:ring-offset-zinc-900">
                        Copy
                    </button>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                    PHREMS calls this with ?month=YYYY-MM to mirror hris_employee_id, commission eligibility, commission scheme, and agent targets.
                </p>
            </div>

            <div class="mt-5">
                <label for="hris_api_token" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                    API Token
                </label>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <input id="hris_api_token"
                           type="text"
                           readonly
                           value="{{ $hrisApiToken }}"
                           class="min-w-0 flex-1 rounded-xl border-slate-300 px-4 py-3 font-mono text-xs shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <button type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('hris_api_token').value); this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500);"
                            class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)] focus:ring-offset-2 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:ring-offset-zinc-900">
                        Copy
                    </button>
                </div>
                <p class="mt-2 text-sm text-slate-500 dark:text-zinc-400">
                    PHREMS can send this as a Bearer token or with the X-HRIS-Token header.
                </p>
            </div>

            <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-100">
                Keep this token private. If you generate a new one, update the connected system immediately so commission slips continue working.
            </div>
        </div>
    </div>
</x-app-layout>
