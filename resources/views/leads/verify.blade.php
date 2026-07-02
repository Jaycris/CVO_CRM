<x-app-layout>
    <x-slot name="header">
        Verify Lead
    </x-slot>

    @php
        $phoneStatusLabels = [
            'Verified' => 'Verified',
            'Voice Mail' => 'Voice Mail',
            'No Answer' => 'No Answer',
            'NIS' => 'NIS - Not in Service',
            'DNC' => 'DNC - Do not call',
            'Wrong Number' => 'Wrong Number',
        ];
        $phoneStatusClasses = [
            'Verified' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-200',
            'Voice Mail' => 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200',
            'No Answer' => 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-200',
            'NIS' => 'bg-rose-100 text-rose-700 dark:bg-rose-400/15 dark:text-rose-200',
            'DNC' => 'bg-red-100 text-red-700 dark:bg-red-400/15 dark:text-red-200',
            'Wrong Number' => 'bg-orange-100 text-orange-700 dark:bg-orange-400/15 dark:text-orange-200',
        ];
        $phoneStatusValues = collect($lead->phone_numbers ?? [])->mapWithKeys(function (string $phoneNumber) use ($lead) {
            return [
                $phoneNumber => in_array($phoneNumber, $lead->verified_phone_numbers ?? [], true)
                    ? 'Verified'
                    : (($lead->phone_number_statuses ?? [])[$phoneNumber] ?? ''),
            ];
        })->all();
        $selectedPhoneStatuses = old('phone_number_statuses', $phoneStatusValues);
    @endphp

    <div class="space-y-6"
         x-data="{
            authorConfirmed: @js(old('author_confirmed', $lead->author_confirmed) ? true : false),
            bookConfirmed: @js(old('book_confirmed', $lead->book_confirmed) ? true : false),
            phoneNumbers: @js($lead->phone_numbers ?? []),
            phoneStatuses: @js($selectedPhoneStatuses),
            emailConfirmed: @js(old('email_confirmed', $lead->email_confirmed) ? true : false),
            verifiedPhoneCount() {
                return Object.values(this.phoneStatuses || {}).filter((status) => status === 'Verified').length;
            },
            score() {
                return (this.authorConfirmed ? 25 : 0)
                    + (this.bookConfirmed ? 25 : 0)
                    + (this.phoneNumbers.length > 0 && this.verifiedPhoneCount() === this.phoneNumbers.length ? 25 : 0)
                    + (this.emailConfirmed ? 25 : 0);
            }
         }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Verify Lead</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Review the lead with your usual sources, then save the verification score.
                </p>
            </div>

            <a href="{{ $returnTo }}"
               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Back to Leads
            </a>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-3">
            <div class="space-y-6 xl:col-span-2">
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Lead Details</h2>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Book Title</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-zinc-100">{{ $lead->book_title }}</p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Author's Name</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-zinc-100">{{ $lead->author_name }}</p>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Phone Number</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($lead->phone_numbers ?? [] as $phoneNumber)
                                    @php
                                        $phoneStatus = $selectedPhoneStatuses[$phoneNumber] ?? '';
                                        $phoneStatusClass = $phoneStatusClasses[$phoneStatus] ?? 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200';
                                    @endphp
                                    <span title="{{ $phoneStatus ? 'Status: '.$phoneStatus : 'Status: Not verified' }}"
                                          class="rounded-full px-3 py-1 text-xs font-semibold {{ $phoneStatusClass }}">
                                        {{ $phoneNumber }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <div class="rounded-xl bg-slate-50 p-4 dark:bg-zinc-950">
                            <p class="text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">Email</p>
                            <p class="mt-1 font-semibold text-slate-900 dark:text-zinc-100">{{ $lead->email ?: 'No email provided' }}</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Verification Sources</h2>

                        @if ($lead->book_link)
                            <a href="{{ $lead->book_link }}" target="_blank" rel="noreferrer"
                               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800">
                                Open Book Link
                            </a>
                        @endif
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-3">
                        @foreach ($verificationLinks as $label => $url)
                            <a href="{{ $url }}" target="_blank" rel="noreferrer"
                               class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-semibold text-slate-700 hover:border-amber-200 hover:bg-amber-50 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-200 dark:hover:border-amber-400/30 dark:hover:bg-amber-400/10">
                                <span>Search {{ $label }}</span>
                                <span class="text-amber-700 dark:text-amber-300">Open</span>
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-zinc-800 dark:bg-zinc-950 dark:text-zinc-300">
                        Phone and email confirmation can be marked after checking your preferred lead source or contact records.
                    </div>
                </div>
            </div>

            <aside class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-zinc-100">Verification Score</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Manual scoring preview</p>
                    </div>

                    <div class="rounded-2xl bg-amber-100 px-4 py-3 text-2xl font-bold text-amber-800 dark:bg-amber-400/15 dark:text-amber-200">
                        <span x-text="score()"></span>
                    </div>
                </div>

                <form method="POST" action="{{ route('leads.verify.store', $lead) }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">

                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        <input type="checkbox" name="author_confirmed" value="1" x-model="authorConfirmed"
                               class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Author Confirmed</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        <input type="checkbox" name="book_confirmed" value="1" x-model="bookConfirmed"
                               class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Book Confirmed</span>
                        </span>
                    </label>

                    <div class="rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Phone Number Status</span>
                            <span class="mt-1 block text-sm text-slate-500 dark:text-zinc-400">
                                A lead only gets the phone score when every phone number is marked Verified.
                            </span>
                        </span>
                    </div>

                    <div class="space-y-2 rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        @foreach ($lead->phone_numbers ?? [] as $phoneNumber)
                            <label class="grid grid-cols-1 gap-2 md:grid-cols-[1fr_180px] md:items-center">
                                <span class="text-sm font-semibold text-slate-800 dark:text-zinc-100">{{ $phoneNumber }}</span>
                                <select name="phone_number_statuses[{{ $phoneNumber }}]"
                                        x-model="phoneStatuses[@js($phoneNumber)]"
                                        class="rounded-xl border-slate-300 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                    <option value="">No status</option>
                                    @foreach ($phoneStatusLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endforeach
                        <x-input-error :messages="$errors->get('phone_number_statuses')" class="mt-2" />
                    </div>

                    <label class="flex items-start gap-3 rounded-xl border border-slate-200 p-4 dark:border-zinc-800">
                        <input type="checkbox" name="email_confirmed" value="1" x-model="emailConfirmed"
                               class="mt-1 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Email Confirmed</span>
                        </span>
                    </label>

                    <div>
                        <label for="verification_notes" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Verification Notes
                        </label>
                        <textarea id="verification_notes" name="verification_notes" rows="4"
                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('verification_notes', $lead->verification_notes) }}</textarea>
                        <x-input-error :messages="$errors->get('verification_notes')" class="mt-2" />
                    </div>

                    <button type="submit"
                            class="w-full rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                        Save Verification
                    </button>
                </form>
            </aside>
        </div>
    </div>
</x-app-layout>
