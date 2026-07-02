<x-app-layout>
    <x-slot name="header">
        Import Leads
    </x-slot>

    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Import Leads</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                    Upload a CSV file to add multiple mined leads at once.
                </p>
            </div>

            <a href="{{ $returnTo }}"
               class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800">
                Back to Leads
            </a>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if (session('import_skipped_rows') && count(session('import_skipped_rows')) > 0)
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                <p class="font-semibold">Some rows were skipped:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach (session('import_skipped_rows') as $skippedRow)
                        <li>{{ $skippedRow }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <form method="POST" action="{{ route('leads.import.store') }}" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <input type="hidden" name="return_to" value="{{ $returnTo }}">

                    <div>
                        <label for="lead_csv" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            CSV File <span class="text-rose-600">*</span>
                        </label>
                        <input id="lead_csv"
                               name="lead_csv"
                               type="file"
                               accept=".csv,text/csv"
                               required
                               class="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm file:mr-4 file:rounded-lg file:border-0 file:bg-zinc-950 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-amber-100 focus:border-amber-500 focus:ring-amber-500 dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 dark:file:bg-amber-400 dark:file:text-zinc-950">
                        <x-input-error :messages="$errors->get('lead_csv')" class="mt-2" />
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <a href="{{ route('leads.import.template') }}"
                           class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-700 shadow-sm hover:bg-amber-100 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-200">
                            Download Template
                        </a>

                        <div class="flex items-center gap-3">
                            <a href="{{ $returnTo }}"
                               class="rounded-xl px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Cancel
                            </a>

                            <button type="submit"
                                    class="rounded-xl bg-zinc-950 px-5 py-3 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:bg-amber-400 dark:text-zinc-950">
                                Import Leads
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl bg-white p-6 text-sm shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <h2 class="font-semibold text-slate-900 dark:text-zinc-100">CSV Columns</h2>
                <div class="mt-4 space-y-3 text-slate-600 dark:text-zinc-300">
                    <p><span class="font-semibold text-slate-900 dark:text-zinc-100">Required:</span> Book Title, Author Name, Phone Numbers.</p>
                    <p><span class="font-semibold text-slate-900 dark:text-zinc-100">Optional:</span> Publisher, Email, Book Link, Published Date.</p>
                    <p>For multiple phone numbers, separate them with a vertical bar: <span class="font-semibold">(602) 446-5352 | (602) 348-3580</span>.</p>
                    <p>Published Date should use <span class="font-semibold">YYYY-MM-DD</span>.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
