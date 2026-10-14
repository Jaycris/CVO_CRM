@if (session('import_summary'))
    @php($importSummary = session('import_summary'))
    <div role="status" @class([
        'rounded-lg border px-4 py-3 text-sm',
        'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100' => $importSummary['skipped'] > 0,
        'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200' => $importSummary['skipped'] === 0,
    ])>
        <p class="font-semibold">{{ $importSummary['skipped'] > 0 ? 'CSV import incomplete' : 'CSV import complete' }}</p>
        <p class="mt-1">{{ $importSummary['total'] }} lead rows in file. {{ $importSummary['imported'] }} imported. {{ $importSummary['skipped'] }} skipped.</p>
        @if ($importSummary['skipped'] > 0)
            <p class="mt-2">Correct the skipped rows below and upload only those rows again.</p>
        @endif
        @if (count(session('import_skipped_rows', [])) > 0)
            <ul class="mt-3 max-h-80 list-disc space-y-1 overflow-y-auto break-words pl-5">
                @foreach (session('import_skipped_rows', []) as $skippedRow)
                    <li>{{ $skippedRow }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
