<x-app-layout>
    <x-slot name="header">
        Create Lead
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Create Lead</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Add author and book details for lead generation tracking.
                </p>
            </div>

            <a href="{{ $returnTo }}"
               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Back to Leads
            </a>
        </div>

        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <form method="POST" action="{{ route('leads.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="return_to" value="{{ $returnTo }}">

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="publisher" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Publisher</label>
                        <input id="publisher" name="publisher" type="text" value="{{ old('publisher') }}"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('publisher')" class="mt-2" />
                    </div>

                    <div>
                        <label for="book_title" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Book Title <span class="text-rose-600">*</span>
                        </label>
                        <input id="book_title" name="book_title" type="text" value="{{ old('book_title') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('book_title')" class="mt-2" />
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="author_name" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Author's Name <span class="text-rose-600">*</span>
                        </label>
                        <input id="author_name" name="author_name" type="text" value="{{ old('author_name') }}" required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('author_name')" class="mt-2" />
                    </div>

                    <div>
                        <label for="email" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <label for="phone_numbers" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                        Phone Number <span class="text-rose-600">*</span>
                    </label>
                    <textarea id="phone_numbers" name="phone_numbers" rows="4" required
                              placeholder="Add one phone number per line"
                              class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:placeholder:text-zinc-500">{{ old('phone_numbers') }}</textarea>
                    <x-input-error :messages="$errors->get('phone_numbers')" class="mt-2" />
                    @if (session('duplicate_phone_numbers'))
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach (session('duplicate_phone_numbers') as $duplicatePhoneNumber)
                                <span class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-400/15 dark:text-rose-200">
                                    {{ $duplicatePhoneNumber }}
                                </span>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="book_link" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Book Link</label>
                        <input id="book_link" name="book_link" type="url" value="{{ old('book_link') }}"
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <x-input-error :messages="$errors->get('book_link')" class="mt-2" />
                    </div>

                    <div>
                        <label for="published_date" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Published Date</label>
                        <x-date-picker id="published_date" name="published_date" :value="old('published_date')"
                                       class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100" />
                        <x-input-error :messages="$errors->get('published_date')" class="mt-2" />
                    </div>
                </div>

                @if ($canSelfMineAndWork)
                    <label class="flex cursor-pointer items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm shadow-sm transition hover:border-emerald-300 hover:bg-emerald-100 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:hover:bg-emerald-400/15">
                        <input type="checkbox" name="work_self" value="1"
                               @checked(old('work_self', true))
                               class="mt-1 rounded border-slate-300 text-emerald-600 shadow-sm focus:ring-emerald-500 dark:border-zinc-700 dark:bg-zinc-950">
                        <span>
                            <span class="block font-semibold text-slate-900 dark:text-zinc-100">Work this lead myself</span>
                            <span class="mt-1 block text-slate-600 dark:text-zinc-300">
                                This assigns the lead to you immediately so it appears in your New Leads and Sales workflow.
                            </span>
                        </span>
                    </label>
                @endif

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ $returnTo }}"
                       class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                        Cancel
                    </a>

                    <button type="submit"
                            class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-400 dark:text-zinc-950">
                        Create Lead
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
