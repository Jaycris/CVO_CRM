<x-app-layout>
    <x-slot name="header">Commission Releases</x-slot>
    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-zinc-100">Commission Releases</h1>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Release status comes from PHREMS. A commission slip marked Sent in PHREMS is treated as Released here.</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-zinc-400">Unreleased disputes cancel the commission in its original period. Released disputes deduct it in the current period. Existing dispute deductions keep their original treatment.</p>
        </div>
        @if (session('success')) <p class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-700">{{ session('success') }}</p> @endif
        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 p-4 text-sm text-rose-700">@foreach ($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>
        @endif
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div><label for="month" class="block text-sm font-semibold">Sales month</label><input id="month" type="month" name="month" value="{{ $month->format('Y-m') }}" class="mt-1 rounded-lg border-slate-300 text-sm dark:bg-zinc-900 dark:text-zinc-100"></div>
            <div><label for="agent" class="block text-sm font-semibold">Agent</label><select id="agent" name="agent_id" class="mt-1 rounded-lg border-slate-300 text-sm dark:bg-zinc-900 dark:text-zinc-100"><option value="">All agents</option>@foreach ($agents as $agent)<option value="{{ $agent->id }}" @selected($agentId == $agent->id)>{{ $agent->first_name }} {{ $agent->last_name }}</option>@endforeach</select></div>
            <button class="rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white">Search</button>
            <a href="{{ route('reports.agent-statements.index', ['month' => $month->format('Y-m'), 'agent_id' => $agentId]) }}" class="px-4 py-2.5 text-sm font-semibold text-slate-600">Agent Statements</a>
        </form>
        <form method="POST" action="{{ route('reports.commission-releases.sync') }}">
            @csrf
            <input type="hidden" name="month" value="{{ $month->format('Y-m') }}"><input type="hidden" name="agent_id" value="{{ $agentId }}">
            <button class="rounded-lg bg-[var(--brand-primary)] px-4 py-2.5 text-sm font-semibold text-white">Fetch status from PHREMS</button>
            <span class="ml-2 text-xs text-slate-500">New disputes check PHREMS automatically. Fetching here does not send slips or release money.</span>
        </form>
        <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <table class="w-full text-left text-sm" style="min-width: 860px">
                <thead class="bg-slate-50 text-xs uppercase text-slate-500 dark:bg-zinc-950"><tr><th class="p-4">Sale / Agent</th><th class="p-4">Calculated USD</th><th class="p-4">PHREMS release status / History</th></tr></thead>
                <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @forelse ($rows as $row)
                        @php
                            $activity = $row['activity'];
                            $events = $history->get($activity->sales_payment_id.':'.$row['agent_id'], collect());
                            $latest = $events->first();
                        @endphp
                        <tr class="align-top">
                            <td class="p-4"><p class="font-semibold">{{ $activity->endorsement_code ?: 'Payment #'.$activity->sales_payment_id }}</p><p>{{ $row['agent']?->first_name }} {{ $row['agent']?->last_name }} ({{ $row['share_role'] }})</p><p class="text-xs text-slate-500">{{ $activity->sold_date?->format('M d, Y') }} · {{ $activity->brand?->imprint_name }}</p><p class="mt-1">{{ $activity->author_name }}</p></td>
                            <td class="p-4 font-semibold">${{ number_format($row['usd_total'], 2) }}</td>
                            <td class="p-4"><p class="font-semibold">{{ $latest ? ($latest->status === 'unknown' ? 'Needs review' : ucfirst($latest->status)) : 'Not confirmed' }}</p>
                                @if ($latest)<p class="text-xs text-slate-500">Last recorded {{ $latest->created_at->format('M d, Y H:i') }} · {{ $latest->source === 'phrems' ? 'PHREMS' : 'Admin' }}</p>@endif
                                @if ($latest?->source_reference)<p class="text-xs text-slate-500">{{ $latest->source_reference }}</p>@endif
                                @if ($latest?->status === 'released')<p class="text-xs">Confirmed released: ${{ number_format((float) ($latest->commission_breakdown['usd_total'] ?? 0), 2) }}</p>@endif
                                @if ($latest?->released_at)<p class="text-xs">Released {{ $latest->released_at->format('M d, Y') }}</p>@endif
                                @if ($events->isNotEmpty())<details class="mt-2"><summary class="cursor-pointer text-xs text-slate-500">Confirmation history ({{ $events->count() }})</summary>
                                    @foreach ($events as $event)<div class="mt-2 border-t border-slate-100 pt-2 text-xs"><p>{{ ucfirst($event->status) }} · {{ $event->created_at->format('M d, Y H:i') }}</p><p>Recorded calculation: ${{ number_format((float) ($event->commission_breakdown['usd_total'] ?? 0), 2) }}</p><p>{{ $event->creator?->first_name }} {{ $event->creator?->last_name }}</p><p class="break-words">{{ $event->reason }}</p></div>@endforeach
                                </details>@endif
                            </td>
                        </tr>
                    @empty <tr><td colspan="3" class="p-8 text-center text-slate-500">No commission-bearing payment records found for this month.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    </div>
</x-app-layout>
