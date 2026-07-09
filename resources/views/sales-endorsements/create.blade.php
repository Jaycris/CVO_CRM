<x-app-layout>
    <x-slot name="header">
        Sales Endorsement Form
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Sales Endorsement Form</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Submit completed sales details for endorsement.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                <p>Please check the form and try again.</p>
            </div>
        @endif

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <form method="POST"
                  action="{{ route('sales.endorsements.store') }}"
                  x-data="{
                    hasFrankie: {{ old('has_frankie') ? 'true' : 'false' }},
                    leadId: @js(old('lead_id')),
                    authorName: @js(old('author_name', '')),
                    bookTitle: @js(old('book_title', '')),
                    contactNumber: @js(old('contact_number', '')),
                    email: @js(old('email', '')),
                    leadOptions: @js($leadOptions),
                    serviceName: @js(old('services', '')),
                    serviceOptions: @js($serviceOptions),
                    frankieAgentId: @js(old('frankie_agent_id', '')),
                    frankieAgentName: @js(collect($frankieAgentOptions)->firstWhere('id', (int) old('frankie_agent_id'))['name'] ?? ''),
                    frankieAgentOptions: @js($frankieAgentOptions),
                    authorDropdownOpen: false,
                    serviceDropdownOpen: false,
                    frankieDropdownOpen: false,
                    filteredLeadOptions() {
                        const search = this.authorName.trim().toLowerCase();

                        if (!search) {
                            return this.leadOptions.slice(0, 8);
                        }

                        return this.leadOptions
                            .filter((item) => {
                                return item.authorName.toLowerCase().includes(search)
                                    || item.bookTitle.toLowerCase().includes(search);
                            })
                            .slice(0, 8);
                    },
                    filteredServiceOptions() {
                        const search = this.serviceName.trim().toLowerCase();

                        if (!search) {
                            return this.serviceOptions.slice(0, 8);
                        }

                        return this.serviceOptions
                            .filter((service) => service.toLowerCase().includes(search))
                            .slice(0, 8);
                    },
                    filteredFrankieAgentOptions() {
                        const search = this.frankieAgentName.trim().toLowerCase();

                        if (!search) {
                            return this.frankieAgentOptions.slice(0, 8);
                        }

                        return this.frankieAgentOptions
                            .filter((agent) => {
                                return agent.name.toLowerCase().includes(search)
                                    || agent.role.toLowerCase().includes(search)
                                    || agent.brand.toLowerCase().includes(search);
                            })
                            .slice(0, 8);
                    },
                    fillLeadDetails() {
                        const normalizedAuthor = this.authorName.trim().toLowerCase();
                        const lead = this.leadOptions.find((item) => item.authorName.toLowerCase() === normalizedAuthor);

                        this.leadId = lead?.id ?? '';

                        if (!lead) {
                            return;
                        }

                        this.bookTitle = lead.bookTitle || this.bookTitle;
                        this.contactNumber = lead.contactNumber || this.contactNumber;
                        this.email = lead.email || this.email;
                    },
                    selectLead(lead) {
                        this.authorName = lead.authorName;
                        this.leadId = lead.id;
                        this.bookTitle = lead.bookTitle || this.bookTitle;
                        this.contactNumber = lead.contactNumber || this.contactNumber;
                        this.email = lead.email || this.email;
                        this.authorDropdownOpen = false;
                    },
                    selectService(service) {
                        this.serviceName = service;
                        this.serviceDropdownOpen = false;
                    },
                    selectFrankieAgent(agent) {
                        this.frankieAgentId = agent.id;
                        this.frankieAgentName = agent.name;
                        this.frankieDropdownOpen = false;
                    }
                  }"
                  class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="agent_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Agent Name <span class="text-rose-600">*</span>
                        </label>
                        <input id="agent_name"
                               type="text"
                               value="{{ trim((auth()->user()->first_name ?? '') . ' ' . (auth()->user()->last_name ?? '')) }}"
                               readonly
                               class="w-full rounded-xl border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700 shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-200">
                    </div>

                    <div class="flex items-end">
                        <label class="flex min-h-[48px] w-full items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 dark:border-zinc-800">
                            <input type="checkbox"
                                   name="has_frankie"
                                   value="1"
                                   x-model="hasFrankie"
                                   class="rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                            <span class="text-sm font-semibold text-slate-800 dark:text-zinc-100">+ Frankie</span>
                        </label>
                    </div>
                </div>

                <div x-show="hasFrankie" x-cloak class="relative" x-on:click.outside="frankieDropdownOpen = false">
                    <label for="frankie_agent_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Frankie Agent Name <span class="text-rose-600">*</span>
                    </label>
                    <input type="hidden" name="frankie_agent_id" x-bind:value="frankieAgentId">
                    <div class="relative">
                        <input id="frankie_agent_name"
                               type="text"
                               x-model="frankieAgentName"
                               x-bind:required="hasFrankie"
                               x-on:focus="frankieDropdownOpen = true"
                               x-on:input.debounce.150ms="frankieAgentId = ''; frankieDropdownOpen = true"
                               x-on:keydown.escape.prevent="frankieDropdownOpen = false"
                               autocomplete="off"
                               placeholder="Search a Sales agent"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 pr-11 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="button"
                                x-on:click="frankieDropdownOpen = !frankieDropdownOpen"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-300"
                                aria-label="Show Frankie agent suggestions">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': frankieDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="frankieDropdownOpen"
                         x-cloak
                         x-transition
                         class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <template x-for="agent in filteredFrankieAgentOptions()" :key="agent.id">
                            <button type="button"
                                    x-on:click="selectFrankieAgent(agent)"
                                    class="block w-full rounded-lg px-3 py-2 text-left hover:bg-amber-50 dark:hover:bg-amber-400/10">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="agent.name"></span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-zinc-400" x-text="`${agent.role} | ${agent.brand}`"></span>
                            </button>
                        </template>

                        <div x-show="filteredFrankieAgentOptions().length === 0" class="px-3 py-4 text-sm text-slate-500 dark:text-zinc-400">
                            No matching Sales agent found.
                        </div>
                    </div>
                    <x-input-error :messages="$errors->get('frankie_agent_id')" class="mt-2" />
                </div>

                <div class="relative" x-on:click.outside="authorDropdownOpen = false">
                    <label for="author_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Author Name <span class="text-rose-600">*</span>
                    </label>
                    <input type="hidden" name="lead_id" x-bind:value="leadId">
                    <div class="relative">
                        <input id="author_name"
                               name="author_name"
                               type="text"
                               x-model="authorName"
                               x-on:focus="authorDropdownOpen = true"
                               x-on:input.debounce.150ms="authorDropdownOpen = true; fillLeadDetails()"
                               x-on:keydown.escape.prevent="authorDropdownOpen = false"
                               required
                               autocomplete="off"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 pr-11 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="button"
                                x-on:click="authorDropdownOpen = !authorDropdownOpen"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-300"
                                aria-label="Show author suggestions">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': authorDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                            </svg>
                        </button>
                    </div>

                    <div x-show="authorDropdownOpen"
                         x-cloak
                         x-transition
                         class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                        <template x-for="lead in filteredLeadOptions()" :key="lead.id">
                            <button type="button"
                                    x-on:click="selectLead(lead)"
                                    class="block w-full rounded-lg px-3 py-2 text-left hover:bg-amber-50 dark:hover:bg-amber-400/10">
                                <span class="block text-sm font-semibold text-slate-900 dark:text-zinc-100" x-text="lead.authorName"></span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500 dark:text-zinc-400" x-text="lead.bookTitle"></span>
                            </button>
                        </template>

                        <div x-show="filteredLeadOptions().length === 0" class="px-3 py-4 text-sm text-slate-500 dark:text-zinc-400">
                            No matching assigned leads found.
                        </div>
                    </div>

                    <x-input-error :messages="$errors->get('author_name')" class="mt-2" />
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="contact_number" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Contact Number <span class="text-rose-600">*</span>
                        </label>
                        <input id="contact_number" name="contact_number" type="text" x-model="contactNumber" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Email
                        </label>
                        <input id="email" name="email" type="email" x-model="email"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <div>
                        <label for="street_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Street Name <span class="text-rose-600">*</span>
                        </label>
                        <input id="street_name" name="street_name" type="text" value="{{ old('street_name') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('street_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="city_state" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            City &amp; State <span class="text-rose-600">*</span>
                        </label>
                        <input id="city_state" name="city_state" type="text" value="{{ old('city_state') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('city_state')" class="mt-2" />
                    </div>

                    <div>
                        <label for="zip_code" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Zip Code <span class="text-rose-600">*</span>
                        </label>
                        <input id="zip_code" name="zip_code" type="text" value="{{ old('zip_code') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('zip_code')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="book_title" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Book Title <span class="text-rose-600">*</span>
                        </label>
                        <input id="book_title" name="book_title" type="text" x-model="bookTitle" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('book_title')" class="mt-2" />
                    </div>

                    <div>
                        <label for="isbn" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            ISBN <span class="text-rose-600">*</span>
                        </label>
                        <input id="isbn" name="isbn" type="text" value="{{ old('isbn') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('isbn')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <div class="relative" x-on:click.outside="serviceDropdownOpen = false">
                        <label for="services" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Services <span class="text-rose-600">*</span>
                        </label>
                        <div class="relative">
                            <input id="services"
                                   name="services"
                                   type="text"
                                   x-model="serviceName"
                                   x-on:focus="serviceDropdownOpen = true"
                                   x-on:input="serviceDropdownOpen = true"
                                   x-on:keydown.escape.prevent="serviceDropdownOpen = false"
                                   autocomplete="off"
                                   required
                                   class="w-full rounded-xl border-slate-300 px-4 py-3 pr-11 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <button type="button"
                                    x-on:click="serviceDropdownOpen = !serviceDropdownOpen"
                                    class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-500 hover:text-amber-700 dark:text-zinc-400 dark:hover:text-amber-300"
                                    aria-label="Show service suggestions">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform" x-bind:class="{ 'rotate-180': serviceDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
                                </svg>
                            </button>
                        </div>

                        <div x-show="serviceDropdownOpen"
                             x-cloak
                             x-transition
                             class="absolute z-30 mt-2 max-h-72 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl dark:border-zinc-700 dark:bg-zinc-900">
                            <template x-for="service in filteredServiceOptions()" :key="service">
                                <button type="button"
                                        x-on:click="selectService(service)"
                                        class="block w-full rounded-lg px-3 py-2 text-left text-sm font-semibold text-slate-900 hover:bg-amber-50 dark:text-zinc-100 dark:hover:bg-amber-400/10"
                                        x-text="service">
                                </button>
                            </template>

                            <div x-show="filteredServiceOptions().length === 0" class="px-3 py-4 text-sm text-slate-500 dark:text-zinc-400">
                                No matching service found. You can still type a custom service.
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('services')" class="mt-2" />
                    </div>

                    <div>
                        <label for="amount" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Amount <span class="text-rose-600">*</span>
                        </label>
                        <input id="amount" name="amount" type="number" min="0" step="0.01" value="{{ old('amount') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>

                    <div>
                        <label for="payment" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Payment <span class="text-rose-600">*</span>
                        </label>
                        <select id="payment" name="payment" required
                                class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="">Select payment</option>
                            @foreach ($paymentOptions as $payment)
                                <option value="{{ $payment }}" @selected(old('payment') === $payment)>{{ $payment }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('payment')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label for="remarks" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Remarks
                    </label>
                    <textarea id="remarks" name="remarks" rows="4"
                              class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('remarks') }}</textarea>
                    <x-input-error :messages="$errors->get('remarks')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <button type="submit"
                            class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-400 dark:text-zinc-950">
                        Submit Endorsement
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
