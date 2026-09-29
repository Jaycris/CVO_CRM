<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\User;
use App\Support\HrisEmployeeLookupClient;
use App\Support\HrisReportsTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function __construct(private readonly HrisEmployeeLookupClient $hrisEmployeeLookupClient)
    {
    }

    public function edit(Request $request): View
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        return view('admin.system-settings.edit', [
            'recordsPerPage' => AppSetting::recordsPerPage(),
            'leadsSalesRecordsPerPage' => AppSetting::leadsSalesRecordsPerPage(),
            'recordsPerPageOptions' => [10, 25, 50, 100],
            'autoReturnUntouchedLeadsHours' => AppSetting::autoReturnUntouchedLeadsHours(),
            'autoReturnUntouchedLeadOptions' => [
                0 => 'Off - do not auto-return leads',
                12 => 'After 12 hours',
                24 => 'After 24 hours',
                48 => 'After 48 hours',
                72 => 'After 3 days',
                168 => 'After 7 days',
            ],
            'maintenanceMode' => AppSetting::maintenanceModeEnabled(),
            'maintenanceReturn' => AppSetting::maintenanceReturn(),
            'hrisApiToken' => AppSetting::hrisApiToken(),
            'hrisBaseUrl' => AppSetting::hrisBaseUrl(),
            'hrisCrmLookupToken' => AppSetting::hrisCrmLookupToken(),
            'commissionSlipApiUrl' => route('api.hris.commission-slip'),
            'salesPerformanceMtdApiUrl' => route('api.hris.sales-performance-mtd'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        $validated = $request->validate([
            'records_per_page' => ['required', 'integer', 'in:10,25,50,100'],
            'leads_sales_records_per_page' => ['required', 'integer', 'in:10,25,50,100'],
            'auto_return_untouched_leads_hours' => ['required', 'integer', 'in:0,12,24,48,72,168'],
            'maintenance_mode' => ['nullable', 'boolean'],
            'maintenance_return' => ['nullable', 'string', 'max:120'],
            'hris_base_url' => ['nullable', 'url', 'max:255'],
            'hris_crm_lookup_token' => ['nullable', 'string', 'max:255'],
        ]);

        AppSetting::set('records_per_page', $validated['records_per_page']);
        AppSetting::set('leads_sales_records_per_page', $validated['leads_sales_records_per_page']);
        AppSetting::set(AppSetting::AUTO_RETURN_UNTOUCHED_LEADS_HOURS_KEY, $validated['auto_return_untouched_leads_hours']);
        AppSetting::set(AppSetting::MAINTENANCE_MODE_KEY, $request->boolean('maintenance_mode') ? '1' : '0');
        AppSetting::set(AppSetting::MAINTENANCE_RETURN_KEY, trim((string) ($validated['maintenance_return'] ?? '')));
        AppSetting::set(AppSetting::HRIS_BASE_URL_KEY, rtrim((string) ($validated['hris_base_url'] ?? ''), '/'));
        AppSetting::set(AppSetting::HRIS_CRM_LOOKUP_TOKEN_KEY, trim((string) ($validated['hris_crm_lookup_token'] ?? '')));

        return back()->with('success', 'System settings updated successfully.');
    }

    public function regenerateApiToken(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        AppSetting::set(AppSetting::HRIS_API_TOKEN_KEY, Str::random(64));

        return back()->with('success', 'HRIS API token generated successfully.');
    }

    public function syncHrisReportsTo(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        $stats = [
            'checked' => 0,
            'updated' => 0,
            'cleared' => 0,
            'matched_by_hris_search' => 0,
            'matched_by_name' => 0,
            'unmatched_name' => 0,
            'missing' => 0,
            'unreadable' => 0,
            'failed' => 0,
        ];

        User::query()
            ->whereNotNull('hris_employee_id')
            ->where('hris_employee_id', '!=', '')
            ->orderBy('id')
            ->chunkById(50, function ($users) use (&$stats): void {
                foreach ($users as $user) {
                    $stats['checked']++;

                    $result = $this->hrisEmployeeLookupClient->show((string) $user->hris_employee_id);

                    if (! $result['available']) {
                        $stats['failed']++;
                        continue;
                    }

                    $payload = $result['payload'] ?? [];
                    $employee = is_array($payload)
                        ? HrisReportsTo::employeeFromPayload($payload)
                        : [];

                    if ($employee === []) {
                        $stats['unreadable']++;
                        continue;
                    }

                    $reportsToHrisEmployeeId = is_array($employee)
                        ? HrisReportsTo::extractHrisEmployeeId($employee)
                        : null;
                    $reportsToName = null;

                    if (! $reportsToHrisEmployeeId && is_array($employee)) {
                        $reportsToName = HrisReportsTo::extractDisplayName($employee);
                        $reportsToHrisEmployeeId = $this->resolveReportsToHrisEmployeeIdFromHris($reportsToName);

                        if ($reportsToHrisEmployeeId) {
                            $stats['matched_by_hris_search']++;
                        } else {
                            $reportsToHrisEmployeeId = $this->resolveReportsToHrisEmployeeId($reportsToName, $user);

                            if ($reportsToHrisEmployeeId) {
                                $stats['matched_by_name']++;
                            }
                        }
                    }

                    if ($reportsToHrisEmployeeId === $user->reports_to_hris_employee_id) {
                        if (! $reportsToHrisEmployeeId) {
                            if ($reportsToName) {
                                $stats['unmatched_name']++;
                            } else {
                                $stats['missing']++;
                            }
                        }

                        continue;
                    }

                    $user->forceFill([
                        'reports_to_hris_employee_id' => $reportsToHrisEmployeeId,
                    ])->save();

                    if ($reportsToHrisEmployeeId) {
                        $stats['updated']++;
                    } else {
                        $stats['cleared']++;
                    }
                }
            });

        $message = "HRIS Reports To sync completed. {$stats['checked']} user(s) checked, {$stats['updated']} updated";

        if ($stats['cleared'] > 0) {
            $message .= ", {$stats['cleared']} cleared";
        }

        if ($stats['matched_by_hris_search'] > 0) {
            $message .= ", {$stats['matched_by_hris_search']} matched by PHREMS Reports To name";
        }

        if ($stats['matched_by_name'] > 0) {
            $message .= ", {$stats['matched_by_name']} matched by CRM Reports To name";
        }

        if ($stats['unmatched_name'] > 0) {
            $message .= ", {$stats['unmatched_name']} had a Reports To name that did not match one CRM user";
        }

        if ($stats['missing'] > 0) {
            $message .= ", {$stats['missing']} had no Reports To from PHREMS/HRIS";
        }

        if ($stats['unreadable'] > 0) {
            $message .= ", {$stats['unreadable']} skipped because PHREMS/HRIS returned an unreadable employee record";
        }

        if ($stats['failed'] > 0) {
            $message .= ", {$stats['failed']} skipped because PHREMS/HRIS was unavailable";
        }

        return back()->with('success', $message.'.');
    }

    private function resolveReportsToHrisEmployeeIdFromHris(?string $reportsToName): ?string
    {
        $reportsToName = trim((string) $reportsToName);

        if ($reportsToName === '') {
            return null;
        }

        $result = $this->hrisEmployeeLookupClient->search($reportsToName, 10);

        if (! $result['available']) {
            return null;
        }

        $payload = $result['payload'] ?? [];
        $employees = is_array($payload)
            ? ($payload['data'] ?? $payload['employees'] ?? $payload['results'] ?? [])
            : [];

        if (! is_array($employees)) {
            return null;
        }

        if (! array_is_list($employees)) {
            $employees = [$employees];
        }

        $normalizedReportsToName = $this->normalizePersonName($reportsToName);
        $matches = collect($employees)
            ->filter(fn ($employee) => is_array($employee))
            ->filter(fn (array $employee) => collect($this->hrisEmployeeNames($employee))
                ->contains(fn (string $name) => $this->normalizePersonName($name) === $normalizedReportsToName))
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return HrisReportsTo::extractEmployeeId($matches->first());
    }

    private function resolveReportsToHrisEmployeeId(?string $reportsToName, User $user): ?string
    {
        $reportsToName = trim((string) $reportsToName);

        if ($reportsToName === '') {
            return null;
        }

        $normalizedReportsToName = $this->normalizePersonName($reportsToName);

        if ($normalizedReportsToName === '') {
            return null;
        }

        $matches = User::query()
            ->whereKeyNot($user->id)
            ->whereNotNull('hris_employee_id')
            ->where('hris_employee_id', '!=', '')
            ->get(['first_name', 'last_name', 'hris_employee_id'])
            ->filter(fn (User $candidate) => $this->normalizePersonName(
                trim($candidate->first_name.' '.$candidate->last_name)
            ) === $normalizedReportsToName)
            ->values();

        if ($matches->count() !== 1) {
            return null;
        }

        return (string) $matches->first()->hris_employee_id;
    }

    private function normalizePersonName(string $name): string
    {
        return Str::of($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    private function hrisEmployeeNames(array $employee): array
    {
        return array_values(array_filter([
            $employee['phone_name'] ?? null,
            $employee['full_name'] ?? null,
            $employee['name'] ?? null,
            $employee['display_name'] ?? null,
            trim(implode(' ', array_filter([
                $employee['first_name'] ?? null,
                $employee['last_name'] ?? null,
            ]))),
        ], fn ($name) => trim((string) $name) !== ''));
    }
}
