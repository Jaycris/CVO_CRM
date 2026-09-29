<x-app-layout>
    <x-slot name="header">
        End of Shift
    </x-slot>

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">End of Shift Reports</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                Submit and review production shift updates.
            </p>
        </div>

        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                <p class="font-semibold">Please check the report and try again.</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($canSubmitEos)
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
                <form method="POST" action="{{ route('production.eos.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <label for="shift_date" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Shift Date <span class="text-rose-600">*</span>
                        </label>
                        <input id="shift_date"
                               name="shift_date"
                               type="date"
                               value="{{ old('shift_date', now()->toDateString()) }}"
                               required
                               class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100 md:w-64">
                    </div>

                    <div>
                        <label for="work_done" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Work Done This Shift <span class="text-rose-600">*</span>
                        </label>
                        <textarea id="work_done"
                                  name="work_done"
                                  rows="5"
                                  required
                                  placeholder="List the tasks, projects, or deliverables completed or worked on."
                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('work_done') }}</textarea>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div>
                            <label for="pending_work" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Pending Work
                            </label>
                            <textarea id="pending_work"
                                      name="pending_work"
                                      rows="4"
                                      placeholder="Anything that needs to continue next shift."
                                      class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('pending_work') }}</textarea>
                        </div>

                        <div>
                            <label for="blockers" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                                Blockers / Concerns
                            </label>
                            <textarea id="blockers"
                                      name="blockers"
                                      rows="4"
                                      placeholder="Issues, missing files, unclear instructions, or help needed."
                                      class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('blockers') }}</textarea>
                        </div>
                    </div>

                    <div>
                        <label for="notes" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">
                            Additional Notes
                        </label>
                        <textarea id="notes"
                                  name="notes"
                                  rows="3"
                                  placeholder="Optional context for Admin or your report-to person."
                                  class="w-full rounded-xl border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('notes') }}</textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="inline-flex min-h-11 items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold shadow-sm transition hover:brightness-95 focus:outline-none focus:ring-2 focus:ring-emerald-600 focus:ring-offset-2 dark:ring-offset-zinc-900"
                                style="background-color: #065f46; color: #ffffff;">
                            Submit EOS Report
                        </button>
                    </div>
                </form>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-slate-200 dark:bg-zinc-900 dark:ring-zinc-800">
            <div class="border-b border-slate-200 px-6 py-4 dark:border-zinc-800">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="font-semibold text-slate-900 dark:text-zinc-100">Report Directory</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                            {{ $canViewAllEos ? 'All submitted EOS reports.' : 'Reports connected to you.' }}
                        </p>
                    </div>

                    <form method="GET" action="{{ route('production.eos.index') }}" class="flex flex-wrap items-center gap-2">
                        <input type="date"
                               name="date"
                               value="{{ request('date') }}"
                               class="h-11 rounded-xl border-slate-300 text-sm shadow-sm focus:border-[var(--brand-primary)] focus:ring-[var(--brand-primary)] dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                        <button type="submit"
                                class="h-11 rounded-xl bg-zinc-950 px-4 text-sm font-semibold text-amber-100 shadow-sm hover:bg-black dark:bg-amber-400 dark:text-zinc-950">
                            Filter
                        </button>
                        @if (request()->filled('date'))
                            <a href="{{ route('production.eos.index') }}"
                               class="rounded-xl px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-100 dark:text-zinc-300 dark:hover:bg-zinc-800">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-zinc-800">
                @forelse ($reports as $report)
                    <article class="p-6">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-slate-900 dark:text-zinc-100">
                                    {{ $report->user?->first_name }} {{ $report->user?->last_name }}
                                </h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">
                                    {{ $report->user?->brand?->imprint_name ?? 'No brand' }} · {{ $report->shift_date?->format('M d, Y') }} · Submitted {{ $report->submitted_at?->format('M d, Y h:i A') }}
                                </p>
                                @if ($report->reportToUser)
                                    <p class="mt-1 text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">
                                        Reports to {{ $report->reportToUser->first_name }} {{ $report->reportToUser->last_name }}
                                    </p>
                                @elseif ($report->report_to_hris_employee_id)
                                    <p class="mt-1 text-xs font-semibold uppercase text-slate-400 dark:text-zinc-500">
                                        Reports to HRIS ID {{ $report->report_to_hris_employee_id }}
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-5 grid gap-4 lg:grid-cols-2">
                            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-950">
                                <p class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Work Done</p>
                                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-zinc-300">{{ $report->work_done }}</p>
                            </div>

                            <div class="space-y-4">
                                @if ($report->pending_work)
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                        <p class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Pending Work</p>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-zinc-300">{{ $report->pending_work }}</p>
                                    </div>
                                @endif
                                @if ($report->blockers)
                                    <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-400/30 dark:bg-amber-400/10">
                                        <p class="text-xs font-bold uppercase text-amber-700 dark:text-amber-200">Blockers / Concerns</p>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-amber-900 dark:text-amber-100">{{ $report->blockers }}</p>
                                    </div>
                                @endif
                                @if ($report->notes)
                                    <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-900">
                                        <p class="text-xs font-bold uppercase text-slate-500 dark:text-zinc-400">Notes</p>
                                        <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-700 dark:text-zinc-300">{{ $report->notes }}</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-6 py-12 text-center text-sm text-slate-500 dark:text-zinc-400">
                        No End of Shift reports yet.
                    </div>
                @endforelse
            </div>

            @if ($reports->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-zinc-800">
                    {{ $reports->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
