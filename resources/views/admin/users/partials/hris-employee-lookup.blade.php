<div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
    <input type="hidden" name="hris_employee_id" x-model="hrisEmployeeId">

    <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
            <label for="hris_employee_search" class="block text-sm font-semibold text-slate-800">HRIS Employee</label>
            <p class="mt-1 text-xs leading-5 text-slate-500">
                Optional. Select an HRIS employee to fill CRM-safe details, or continue manually.
            </p>
        </div>

        <template x-if="hrisEmployeeId">
            <button type="button"
                    x-on:click="clearHrisEmployee()"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-600 shadow-sm hover:bg-slate-100">
                <span x-text="hrisMode === 'edit' ? 'Unlink' : 'Clear'"></span>
            </button>
        </template>
    </div>

    <div class="mt-4">
        <div class="relative">
            <input id="hris_employee_search"
                   type="search"
                   x-model="hrisQuery"
                   x-on:input.debounce.300ms="searchHrisEmployees()"
                   x-on:focus="searchHrisEmployees()"
                   placeholder="Search by HRIS ID, phone name, or company email"
                   autocomplete="off"
                   class="w-full rounded-xl border-slate-300 bg-white px-4 py-3 pr-12 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500">
            <div x-show="hrisLoading" x-cloak class="absolute right-4 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin rounded-full border-2 border-slate-300 border-t-amber-500"></div>
        </div>

        <p x-show="hrisUnavailable" x-cloak class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            HRIS is unavailable. You can continue creating this user manually
        </p>

        <p x-show="!hrisUnavailable && hrisEmpty" x-cloak class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
            No matching HRIS employee. You can continue creating this user manually.
        </p>

        <p x-show="hrisLinkedWarning" x-cloak class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700" x-text="hrisLinkedWarning"></p>

        <div x-show="!hrisUnavailable && hrisResults.length > 0" x-cloak class="mt-3 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <template x-for="employee in hrisResults" :key="employee.hris_employee_id">
                <button type="button"
                        x-on:click="selectHrisEmployee(employee)"
                        x-bind:disabled="!employee.is_active && hrisMode === 'create'"
                        class="flex w-full items-start justify-between gap-4 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50">
                    <span>
                        <span class="block text-sm font-bold text-slate-900" x-text="employee.phone_name || [employee.first_name, employee.last_name].filter(Boolean).join(' ') || employee.hris_employee_id"></span>
                        <span class="mt-1 block text-xs text-slate-500">
                            <span x-text="employee.hris_employee_id"></span>
                            <span> · </span>
                            <span x-text="employee.department || 'No department'"></span>
                            <span> · </span>
                            <span x-text="employee.workplace_type || employee.work_type || 'No workplace type'"></span>
                            <span> · </span>
                            <span x-text="employee.email || 'No company email'"></span>
                        </span>
                    </span>
                    <span x-show="!employee.is_active" class="shrink-0 rounded-full bg-slate-100 px-2 py-1 text-[10px] font-bold uppercase text-slate-500">Inactive</span>
                </button>
            </template>
        </div>

        <div x-show="hrisEmployeeId" x-cloak class="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            <span class="font-bold">Linked HRIS Employee:</span>
            <span x-text="hrisSelectedLabel"></span>
            <span class="font-semibold" x-text="'(' + hrisEmployeeId + ')'"></span>
        </div>

        <x-input-error :messages="$errors->get('hris_employee_id')" class="mt-2" />
    </div>
</div>

@once
    <script>
        window.hrisEmployeeLookup = (config) => ({
            hrisMode: config.mode || 'create',
            hrisHealthUrl: config.healthUrl,
            hrisSearchUrl: config.searchUrl,
            hrisShowUrlTemplate: config.showUrlTemplate,
            hrisEmployeeId: config.initialEmployeeId || '',
            hrisSelectedLabel: config.initialSelectedLabel || '',
            hrisQuery: '',
            hrisResults: [],
            hrisLoading: false,
            hrisUnavailable: false,
            hrisEmpty: false,
            hrisLinkedWarning: '',

            init() {
                this.checkHris();

                if (this.hrisEmployeeId) {
                    this.loadLinkedHrisEmployee();
                }
            },

            async checkHris() {
                try {
                    const response = await fetch(this.hrisHealthUrl, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();
                    this.hrisUnavailable = !payload.available;
                } catch (error) {
                    this.hrisUnavailable = true;
                }
            },

            async searchHrisEmployees() {
                if (this.hrisUnavailable || this.hrisQuery.trim().length < 2) {
                    this.hrisResults = [];
                    this.hrisEmpty = false;
                    return;
                }

                this.hrisLoading = true;
                this.hrisEmpty = false;

                try {
                    const url = new URL(this.hrisSearchUrl, window.location.origin);
                    url.searchParams.set('q', this.hrisQuery.trim());
                    url.searchParams.set('limit', '15');

                    const response = await fetch(url, { headers: { Accept: 'application/json' } });
                    const payload = await response.json();

                    if (!payload.available) {
                        this.hrisUnavailable = true;
                        this.hrisResults = [];
                        return;
                    }

                    this.hrisResults = (payload.data || []).filter((employee) => this.hrisMode !== 'create' || employee.is_active);
                    this.hrisEmpty = this.hrisResults.length === 0;
                } catch (error) {
                    this.hrisUnavailable = true;
                    this.hrisResults = [];
                } finally {
                    this.hrisLoading = false;
                }
            },

            async loadLinkedHrisEmployee() {
                try {
                    const response = await fetch(this.hrisShowUrlTemplate.replace('__ID__', encodeURIComponent(this.hrisEmployeeId)), {
                        headers: { Accept: 'application/json' },
                    });
                    const payload = await response.json();

                    if (!payload.available) {
                        this.hrisUnavailable = true;
                        return;
                    }

                    if (payload.employee && payload.employee.hris_employee_id) {
                        this.hrisSelectedLabel = payload.employee.phone_name || [payload.employee.first_name, payload.employee.last_name].filter(Boolean).join(' ');
                        this.hrisLinkedWarning = '';
                    } else if (payload.message) {
                        this.hrisLinkedWarning = payload.message;
                    }
                } catch (error) {
                    this.hrisUnavailable = true;
                }
            },

            selectHrisEmployee(employee) {
                this.hrisEmployeeId = employee.hris_employee_id || '';
                this.hrisSelectedLabel = employee.phone_name || [employee.first_name, employee.last_name].filter(Boolean).join(' ') || this.hrisEmployeeId;
                this.hrisQuery = '';
                this.hrisResults = [];
                this.hrisEmpty = false;
                this.hrisLinkedWarning = '';

                const name = this.crmNameFromEmployee(employee);
                this.fillField('first_name', name.firstName);
                this.fillField('last_name', name.lastName);
                this.fillField('email', employee.email || '');

                if (employee.department) {
                    this.department = employee.department;

                    if (typeof this.changeDepartment === 'function') {
                        this.changeDepartment();
                    }
                }

                const workType = this.normalizeWorkType(employee.workplace_type || employee.work_arrangement || employee.work_type || '');
                if (workType) {
                    this.fillField('work_type', workType);
                }
            },

            clearHrisEmployee() {
                this.hrisEmployeeId = '';
                this.hrisSelectedLabel = '';
                this.hrisQuery = '';
                this.hrisResults = [];
                this.hrisEmpty = false;
                this.hrisLinkedWarning = '';
            },

            fillField(id, value) {
                const field = document.getElementById(id);

                if (!field) {
                    return;
                }

                field.value = value;
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            },

            normalizeWorkType(value) {
                const normalized = String(value).toLowerCase().replace(/[\s-]+/g, '_');
                const map = {
                    remote: 'remote',
                    hybrid: 'hybrid',
                    site: 'site',
                    on_site: 'site',
                    onsite: 'site',
                };

                return map[normalized] || '';
            },

            crmNameFromEmployee(employee) {
                const phoneName = String(employee.phone_name || '').trim();
                const hrisFirstName = String(employee.first_name || '').trim();
                const hrisLastName = String(employee.last_name || '').trim();

                if (!phoneName) {
                    return {
                        firstName: hrisFirstName,
                        lastName: hrisLastName,
                    };
                }

                const parts = phoneName.split(/\s+/).filter(Boolean);

                if (parts.length > 1) {
                    return {
                        firstName: parts.shift(),
                        lastName: parts.join(' '),
                    };
                }

                return {
                    firstName: phoneName,
                    lastName: hrisLastName,
                };
            },
        });
    </script>
@endonce
