<?php

namespace App\Http\Controllers;

use App\Models\ProductionProject;
use App\Models\SalesActivity;
use App\Models\SalesPayment;
use App\Support\BrandScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('view_reports')
            || (bool) $request->user()?->hasPermission('view_sales_activity')
            || (bool) $request->user()?->hasPermission('view_sold_mined_leads')
            || (bool) $request->user()?->hasPermission('view_verified_sold_leads')
            || (bool) $request->user()?->hasPermission('view_production_reports'),
            403
        );

        $validated = $request->validate([
            'report_type' => ['nullable', 'string', 'in:sales,finance,production,lead_miner'],
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        $reportType = $validated['report_type'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;

        [$activities, $payments, $productionProjects] = $reportType
            ? $this->reportCollections($request, $reportType, $search, $startDate, $endDate)
            : [collect(), collect(), collect()];

        $summaryCards = [
            [
                'label' => 'Total Sales',
                'count' => '$' . number_format((float) $activities->sum('amount'), 2),
                'hint' => 'Successful payments',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Successful Payments',
                'count' => $payments->where('status', 'Payment Success')->count(),
                'hint' => 'Finance confirmed',
                'tone' => 'sky',
            ],
            [
                'label' => 'Refunds / Disputes',
                'count' => $payments->whereIn('status', ['Refund', 'Dispute', 'Declined'])->count(),
                'hint' => 'Needs finance review',
                'tone' => 'rose',
            ],
            [
                'label' => 'Production Projects',
                'count' => $productionProjects->count(),
                'hint' => 'Generated from sales',
                'tone' => 'amber',
            ],
        ];

        $summaryCards = $reportType ? $summaryCards : [];

        return view('reports.index', [
            'summaryCards' => $summaryCards,
            'reportRows' => $reportType ? $this->reportRows($activities, $payments, $productionProjects, $reportType) : collect(),
            'salesByAgent' => $this->sumSalesCreditByAgent($activities),
            'salesByBrand' => $this->sumByLabel($activities, fn (SalesActivity $activity) => $activity->brand?->imprint_name ?: 'No Brand'),
            'salesByMonth' => $this->sumByLabel($activities, fn (SalesActivity $activity) => $activity->sold_date?->format('F Y') ?: 'No Date'),
            'salesByService' => $this->sumByLabel($activities, fn (SalesActivity $activity) => $activity->service_name ?: 'No Service'),
            'leadMinerCredits' => $this->sumByPerson($activities, 'leadMiner'),
            'verifierCredits' => $this->sumByPerson($activities, 'verifier'),
            'financeStatusCounts' => $payments->groupBy('status')->map(fn (Collection $items, string $status) => [
                'label' => $status ?: 'No Status',
                'count' => $items->count(),
            ])->sortBy('label')->values(),
            'productionByStatus' => $productionProjects->groupBy('status')->map(fn (Collection $items, string $status) => [
                'label' => str($status ?: 'pending')->replace('_', ' ')->title()->toString(),
                'count' => $items->count(),
            ])->sortBy('label')->values(),
            'reportSearchOptions' => $this->reportSearchOptions($request),
            'filters' => [
                'report_type' => $reportType,
                'search' => $search,
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    public function export(Request $request, string $format): StreamedResponse|\Illuminate\Http\Response
    {
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);
        abort_unless(
            $request->user()?->role?->name === 'Admin'
            || (bool) $request->user()?->hasPermission('view_reports')
            || (bool) $request->user()?->hasPermission('view_sales_activity')
            || (bool) $request->user()?->hasPermission('view_sold_mined_leads')
            || (bool) $request->user()?->hasPermission('view_verified_sold_leads')
            || (bool) $request->user()?->hasPermission('view_production_reports'),
            403
        );

        $validated = $request->validate([
            'report_type' => ['required', 'string', 'in:sales,finance,production,lead_miner'],
            'search' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        [$activities, $payments, $productionProjects] = $this->reportCollections(
            $request,
            $validated['report_type'],
            trim((string) ($validated['search'] ?? '')),
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        $rows = $this->reportRows($activities, $payments, $productionProjects, $validated['report_type']);
        $filename = str($validated['report_type'])->replace('_', '-')->append('-report-', now()->format('Ymd-His'));

        if ($format === 'csv') {
            return Response::streamDownload(function () use ($rows) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Type', 'Date', 'Reference', 'Brand / Account', 'Author', 'Book Title', 'Agent', 'Status', 'Amount']);

                $rows->each(function (array $row) use ($handle) {
                    fputcsv($handle, [
                        $row['type'],
                        $row['date']?->format('Y-m-d') ?: '',
                        $row['reference'],
                        $row['brand'],
                        $row['author'],
                        $row['book_title'],
                        $row['agent'],
                        $row['status'],
                        is_null($row['amount']) ? '' : number_format((float) $row['amount'], 2, '.', ''),
                    ]);
                });

                fclose($handle);
            }, "{$filename}.csv", [
                'Content-Type' => 'text/csv',
            ]);
        }

        return response($this->reportPdf($rows, str($validated['report_type'])->replace('_', ' ')->title()->toString() . ' Report'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }

    private function reportCollections(Request $request, ?string $reportType, string $search, ?string $startDate, ?string $endDate): array
    {
        $activities = collect();
        $payments = collect();
        $productionProjects = collect();

        if (in_array($reportType, ['sales', 'lead_miner'], true)) {
            $activities = SalesActivity::with(['brand', 'agent', 'frankieAgent', 'leadMiner', 'verifier', 'service'])
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->where('payment_status', 'Payment Success')
                ->when($startDate, fn ($query) => $query->whereDate('sold_date', '>=', $startDate))
                ->when($endDate, fn ($query) => $query->whereDate('sold_date', '<=', $endDate))
                ->when($search !== '', fn ($query) => $this->filterSalesActivities($query, $search))
                ->get();
        }

        if ($reportType === 'finance') {
            $payments = SalesPayment::with(['brand', 'endorsement.agent'])
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->when($startDate, fn ($query) => $query->whereDate('sold_date', '>=', $startDate))
                ->when($endDate, fn ($query) => $query->whereDate('sold_date', '<=', $endDate))
                ->when($search !== '', fn ($query) => $this->filterPayments($query, $search))
                ->get();
        }

        if ($reportType === 'production') {
            $productionProjects = ProductionProject::with(['brand', 'endorsement.agent', 'tasks'])
                ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
                ->when($startDate, fn ($query) => $query->whereDate('endorsed_at', '>=', $startDate))
                ->when($endDate, fn ($query) => $query->whereDate('endorsed_at', '<=', $endDate))
                ->when($search !== '', fn ($query) => $this->filterProductionProjects($query, $search))
                ->get();
        }

        return [$activities, $payments, $productionProjects];
    }

    private function reportPdf(Collection $rows, string $title): string
    {
        $lines = collect([
            $title,
            'Generated: ' . now()->format('M d, Y h:i A'),
            '',
            'Type | Date | Reference | Brand | Author | Book | Agent | Status | Amount',
            str_repeat('-', 110),
        ]);

        $rows->take(36)->each(function (array $row) use ($lines) {
            $lines->push(implode(' | ', [
                $row['type'],
                $row['date']?->format('Y-m-d') ?: '-',
                $row['reference'],
                $this->pdfText($row['brand'], 18),
                $this->pdfText($row['author'], 18),
                $this->pdfText($row['book_title'], 24),
                $this->pdfText($row['agent'], 18),
                $row['status'],
                is_null($row['amount']) ? '-' : '$' . number_format((float) $row['amount'], 2),
            ]));
        });

        if ($rows->count() > 36) {
            $lines->push('');
            $lines->push('Showing first 36 records. Export CSV for the complete data.');
        }

        if ($rows->isEmpty()) {
            $lines->push('No report data matched the selected filter.');
        }

        $content = "BT\n/F1 9 Tf\n50 790 Td\n12 TL\n";
        foreach ($lines as $line) {
            $content .= '(' . $this->pdfEscape($line) . ") Tj\nT*\n";
        }
        $content .= "ET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n",
            "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n",
            "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object;
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function pdfText(string $value, int $length): string
    {
        return str($value)->limit($length, '...')->toString();
    }

    private function pdfEscape(string $value): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }

    private function reportRows(Collection $activities, Collection $payments, Collection $productionProjects, string $reportType): Collection
    {
        if ($reportType === 'finance') {
            return $payments->map(function (SalesPayment $payment) {
                $endorsement = $payment->endorsement;
                $agentName = trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: '-';

                return [
                    'type' => 'Finance',
                    'date' => $payment->sold_date,
                    'reference' => $endorsement?->endorsement_code ?: '-',
                    'brand' => $payment->brand?->imprint_name ?: '-',
                    'author' => $endorsement?->author_name ?: '-',
                    'book_title' => $endorsement?->book_title ?: '-',
                    'agent' => $agentName,
                    'status' => $payment->status ?: '-',
                    'amount' => (float) ($endorsement?->amount ?? 0),
                ];
            })->sortByDesc(fn (array $row) => $row['date']?->timestamp ?? 0)->values();
        }

        if ($reportType === 'production') {
            return $productionProjects->map(function (ProductionProject $project) {
                $endorsement = $project->endorsement;
                $agentName = trim(($endorsement?->agent?->first_name ?? '') . ' ' . ($endorsement?->agent?->last_name ?? '')) ?: '-';

                return [
                    'type' => 'Production',
                    'date' => $project->endorsed_at,
                    'reference' => 'PRJ-' . str_pad((string) $project->id, 5, '0', STR_PAD_LEFT),
                    'brand' => $project->brand?->imprint_name ?: '-',
                    'author' => $endorsement?->author_name ?: '-',
                    'book_title' => $endorsement?->book_title ?: '-',
                    'agent' => $agentName,
                    'status' => str($project->status ?: 'pending')->replace('_', ' ')->title()->toString(),
                    'amount' => null,
                ];
            })->sortByDesc(fn (array $row) => $row['date']?->timestamp ?? 0)->values();
        }

        return $activities
            ->map(function (SalesActivity $activity) use ($reportType) {
                $agentName = trim(($activity->agent?->first_name ?? '') . ' ' . ($activity->agent?->last_name ?? '')) ?: '-';
                $leadMinerName = trim(($activity->leadMiner?->first_name ?? '') . ' ' . ($activity->leadMiner?->last_name ?? '')) ?: '-';

                return [
                    'type' => $reportType === 'lead_miner' ? 'Lead Miner Sale' : 'Sales',
                    'date' => $activity->sold_date,
                    'reference' => $activity->endorsement_code ?: '-',
                    'brand' => $activity->brand?->imprint_name ?: '-',
                    'author' => $activity->author_name ?: '-',
                    'book_title' => $activity->book_title ?: '-',
                    'agent' => $reportType === 'lead_miner' ? $leadMinerName : $agentName,
                    'status' => $activity->payment_status ?: '-',
                    'amount' => (float) $activity->amount,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['date']?->timestamp ?? 0)
            ->values();
    }

    private function reportSearchOptions(Request $request): Collection
    {
        $options = collect();

        SalesActivity::with(['brand', 'agent', 'frankieAgent', 'service'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->latest('sold_date')
            ->limit(50)
            ->get()
            ->each(function (SalesActivity $activity) use ($options) {
                $agentName = trim(($activity->agent?->first_name ?? '') . ' ' . ($activity->agent?->last_name ?? '')) ?: 'Unassigned Agent';
                $frankieName = trim(($activity->frankieAgent?->first_name ?? '') . ' ' . ($activity->frankieAgent?->last_name ?? ''));
                $brandName = $activity->brand?->imprint_name ?: 'No Brand';
                $serviceName = $activity->service_name ?: $activity->service?->service_name ?: 'No Service';

                $this->pushReportSearchOption($options, $activity->endorsement_code, "SE ID: {$activity->endorsement_code}", "{$activity->author_name} | {$activity->book_title}");
                $this->pushReportSearchOption($options, $activity->author_name, "Author: {$activity->author_name}", "{$activity->book_title} | Agent: {$agentName}");
                $this->pushReportSearchOption($options, $activity->book_title, "Book: {$activity->book_title}", "{$activity->author_name} | {$serviceName}");
                $this->pushReportSearchOption($options, $agentName, "Agent: {$agentName}", "{$brandName} | {$serviceName}");
                $this->pushReportSearchOption($options, $frankieName, "Frankie Agent: {$frankieName}", "{$brandName} | {$serviceName}");
                $this->pushReportSearchOption($options, $brandName, "Brand: {$brandName}", "{$serviceName}");
                $this->pushReportSearchOption($options, $serviceName, "Service: {$serviceName}", "{$brandName}");
            });

        SalesPayment::with(['brand', 'endorsement.agent'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->latest('sold_date')
            ->limit(50)
            ->get()
            ->each(function (SalesPayment $payment) use ($options) {
                $endorsement = $payment->endorsement;

                if (! $endorsement) {
                    return;
                }

                $agentName = trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? '')) ?: 'Unassigned Agent';
                $brandName = $payment->brand?->imprint_name ?: 'No Brand';

                $this->pushReportSearchOption($options, $endorsement->endorsement_code, "SE ID: {$endorsement->endorsement_code}", "{$endorsement->author_name} | {$payment->status}");
                $this->pushReportSearchOption($options, $payment->status, "Payment Status: {$payment->status}", "{$endorsement->author_name} | {$brandName}");
                $this->pushReportSearchOption($options, $payment->payment_method, "Payment Method: {$payment->payment_method}", "{$endorsement->author_name} | {$agentName}");
            });

        ProductionProject::with(['brand', 'endorsement.agent'])
            ->tap(fn ($query) => BrandScope::apply($query, $request->user()))
            ->latest('endorsed_at')
            ->limit(50)
            ->get()
            ->each(function (ProductionProject $project) use ($options) {
                $endorsement = $project->endorsement;

                if (! $endorsement) {
                    return;
                }

                $agentName = trim(($endorsement->agent?->first_name ?? '') . ' ' . ($endorsement->agent?->last_name ?? '')) ?: 'Unassigned Agent';
                $brandName = $project->brand?->imprint_name ?: 'No Brand';
                $projectCode = 'PRJ-' . str_pad((string) $project->id, 5, '0', STR_PAD_LEFT);

                $this->pushReportSearchOption($options, $projectCode, "Project: {$projectCode}", "{$endorsement->author_name} | {$project->tracker_type}");
                $this->pushReportSearchOption($options, $project->tracker_type, "Production Category: {$project->tracker_type}", "{$brandName} | Agent: {$agentName}");
                $this->pushReportSearchOption($options, $project->status, "Production Status: {$project->status}", "{$endorsement->author_name} | {$brandName}");
            });

        return $options
            ->unique(fn (array $option) => strtolower($option['value'] . '|' . $option['label']))
            ->values()
            ->take(80);
    }

    private function pushReportSearchOption(Collection $options, ?string $value, string $label, string $helper = ''): void
    {
        $value = trim((string) $value);

        if ($value === '') {
            return;
        }

        $options->push([
            'value' => $value,
            'label' => $label,
            'helper' => $helper,
        ]);
    }

    private function filterSalesActivities($query, string $search): void
    {
        $query->where(function ($query) use ($search) {
            $query->where('endorsement_code', 'like', "%{$search}%")
                ->orWhere('author_name', 'like', "%{$search}%")
                ->orWhere('book_title', 'like', "%{$search}%")
                ->orWhere('service_name', 'like', "%{$search}%")
                ->orWhere('payment_method', 'like', "%{$search}%")
                ->orWhere('payment_status', 'like', "%{$search}%")
                ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                ->orWhereHas('agent', fn ($query) => $this->filterUserName($query, $search))
                ->orWhereHas('frankieAgent', fn ($query) => $this->filterUserName($query, $search))
                ->orWhereHas('leadMiner', fn ($query) => $this->filterUserName($query, $search))
                ->orWhereHas('verifier', fn ($query) => $this->filterUserName($query, $search));
        });
    }

    private function filterPayments($query, string $search): void
    {
        $query->where(function ($query) use ($search) {
            $query->where('payment_method', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                ->orWhereHas('endorsement', function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('services', 'like', "%{$search}%")
                        ->orWhereHas('agent', fn ($query) => $this->filterUserName($query, $search));
                });
        });
    }

    private function filterProductionProjects($query, string $search): void
    {
        $query->where(function ($query) use ($search) {
            $query->where('tracker_type', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhereHas('brand', fn ($query) => $query->where('imprint_name', 'like', "%{$search}%"))
                ->orWhereHas('endorsement', function ($query) use ($search) {
                    $query->where('endorsement_code', 'like', "%{$search}%")
                        ->orWhere('author_name', 'like', "%{$search}%")
                        ->orWhere('book_title', 'like', "%{$search}%")
                        ->orWhere('services', 'like', "%{$search}%")
                        ->orWhereHas('agent', fn ($query) => $this->filterUserName($query, $search));
                });
        });
    }

    private function filterUserName($query, string $search): void
    {
        $query->where('first_name', 'like', "%{$search}%")
            ->orWhere('last_name', 'like', "%{$search}%")
            ->orWhereRaw("concat(first_name, ' ', last_name) like ?", ["%{$search}%"]);
    }

    private function sumByPerson(Collection $activities, string $relation): Collection
    {
        return $activities
            ->groupBy(function (SalesActivity $activity) use ($relation) {
                $user = $activity->{$relation};

                return trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: 'Unassigned';
            })
            ->map(fn (Collection $items, string $name) => [
                'label' => $name,
                'count' => $items->count(),
                'total' => (float) $items->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function sumSalesCreditByAgent(Collection $activities): Collection
    {
        return $activities
            ->flatMap(function (SalesActivity $activity) {
                $rows = collect();
                $agentName = trim(($activity->agent?->first_name ?? '') . ' ' . ($activity->agent?->last_name ?? '')) ?: 'Unassigned';

                $rows->push([
                    'label' => $agentName,
                    'amount' => (float) ($activity->agent_credit_amount ?: $activity->amount),
                ]);

                if ($activity->frankieAgent && (float) $activity->frankie_credit_amount > 0) {
                    $frankieName = trim(($activity->frankieAgent?->first_name ?? '') . ' ' . ($activity->frankieAgent?->last_name ?? '')) ?: 'Unassigned';

                    $rows->push([
                        'label' => $frankieName,
                        'amount' => (float) $activity->frankie_credit_amount,
                    ]);
                }

                return $rows;
            })
            ->groupBy('label')
            ->map(fn (Collection $items, string $name) => [
                'label' => $name,
                'count' => $items->count(),
                'total' => (float) $items->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    private function sumByLabel(Collection $activities, callable $labelResolver): Collection
    {
        return $activities
            ->groupBy($labelResolver)
            ->map(fn (Collection $items, string $label) => [
                'label' => $label,
                'count' => $items->count(),
                'total' => (float) $items->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }
}
