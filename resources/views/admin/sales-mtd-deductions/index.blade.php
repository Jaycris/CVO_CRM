<x-app-layout>
    <x-slot name="header">Sales MTD Deductions</x-slot>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Sales MTD Deductions</h1>
            <a href="{{ route('reports.sales-performance.index', ['month' => $month->format('Y-m')]) }}" class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Back to Sales Dashboard</a>
        </div>
        @if (session('success'))
            <div role="status" class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div role="alert" class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-200">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        <section class="border-b border-slate-200 pb-6 dark:border-zinc-800">
            <h2 class="mb-4 text-lg font-semibold text-slate-900 dark:text-zinc-100">New Deduction</h2>
            <form method="POST" action="{{ route('admin.sales-mtd-deductions.store') }}" class="space-y-4" x-data="{ submitting: false }" x-on:submit="submitting = true">
                @csrf
                <input type="hidden" name="request_key" value="{{ old('request_key', (string) \Illuminate\Support\Str::uuid()) }}">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <div class="min-w-0">
                        <label for="agent_id" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Sales Agent</label>
                        <select id="agent_id" name="agent_id" required class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                            <option value="">Select agent</option>
                            @foreach ($agents as $agent)
                                <option value="{{ $agent->id }}" @selected((string) old('agent_id') === (string) $agent->id)>{{ trim($agent->first_name . ' ' . $agent->last_name) }} - {{ $agent->brand?->imprint_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="month" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Effective Month</label>
                        <input id="month" name="month" type="month" required value="{{ old('month', $month->format('Y-m')) }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>
                    <div>
                        <label for="amount" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Deduction (USD)</label>
                        <input id="amount" name="amount" type="number" min="0.01" max="999999999.99" step="0.01" required value="{{ old('amount') }}" class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    </div>
                </div>
                <div>
                    <label for="reason" class="mb-2 block text-sm font-medium text-slate-700 dark:text-zinc-300">Reason</label>
                    <textarea id="reason" name="reason" rows="2" required maxlength="1000" class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">{{ old('reason') }}</textarea>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="rounded-lg bg-emerald-700 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">Record Deduction</button>
                </div>
            </form>
        </section>
        <section>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-slate-900 dark:text-zinc-100">Deduction History</h2>
                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <label for="history_month" class="sr-only">History month</label>
                    <input id="history_month" name="month" type="month" required value="{{ $month->format('Y-m') }}" class="rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                    <button class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 dark:border-zinc-700 dark:text-zinc-300">Filter</button>
                </form>
            </div>
            <div class="overflow-x-auto rounded-lg border border-slate-200 dark:border-zinc-800">
                <table class="w-full text-left text-sm" style="min-width: 960px;">
                    <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-800 dark:text-zinc-400">
                        <tr><th class="px-4 py-3">Agent / Month</th><th class="px-4 py-3">Amount</th><th class="px-4 py-3">Reason</th><th class="px-4 py-3">Recorded By</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Action</th></tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                        @forelse ($deductions as $deduction)
                            <tr class="align-top text-slate-700 dark:text-zinc-300">
                                <td class="px-4 py-3"><p class="font-semibold">{{ trim($deduction->agent?->first_name . ' ' . $deduction->agent?->last_name) }}</p><p class="mt-1 text-xs text-slate-500">{{ $deduction->brand?->imprint_name }}</p><p class="mt-1 text-xs">{{ $deduction->effective_month->format('M Y') }}</p></td>
                                <td class="whitespace-nowrap px-4 py-3 font-semibold text-rose-600 dark:text-rose-300">-${{ number_format((float) $deduction->amount, 2) }}</td>
                                <td class="break-words px-4 py-3" style="max-width: 320px;">{{ $deduction->reason }}</td>
                                <td class="px-4 py-3"><p>{{ trim($deduction->creator?->first_name . ' ' . $deduction->creator?->last_name) ?: 'Former user' }}</p><p class="mt-1 text-xs">{{ $deduction->created_at->timezone(config('app.timezone'))->format('M d, Y h:i A') }}</p></td>
                                <td class="px-4 py-3">
                                    @if ($deduction->voided_at)
                                        <span class="text-slate-500">Voided</span>
                                        <p class="mt-1 text-xs">{{ $deduction->void_reason }}</p>
                                        <p class="mt-1 text-xs">{{ trim($deduction->voidedBy?->first_name . ' ' . $deduction->voidedBy?->last_name) }}</p>
                                        <p class="mt-1 text-xs">{{ $deduction->voided_at->timezone(config('app.timezone'))->format('M d, Y h:i A') }}</p>
                                    @else
                                        <span class="text-emerald-700 dark:text-emerald-300">Active</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @unless ($deduction->voided_at)
                                        <form method="POST" action="{{ route('admin.sales-mtd-deductions.void', $deduction) }}" class="flex flex-col gap-2" onsubmit="return confirm('Void this deduction and restore the agent MTD?');">
                                            @csrf
                                            <input name="void_reason" aria-label="Reason for voiding deduction {{ $deduction->id }}" placeholder="Void reason" required maxlength="1000" class="w-full rounded-lg border-slate-300 text-sm dark:border-zinc-700 dark:bg-zinc-950 dark:text-zinc-100">
                                            <button class="rounded-lg border border-rose-200 px-3 py-2 text-sm font-semibold text-rose-700 dark:border-rose-400/30 dark:text-rose-200">Void</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-500">No deductions for {{ $month->format('F Y') }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $deductions->links() }}</div>
        </section>
    </div>
</x-app-layout>
