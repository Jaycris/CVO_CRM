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

                    $employee = $result['payload']['data'] ?? $result['payload'] ?? [];
                    $reportsToHrisEmployeeId = is_array($employee)
                        ? HrisReportsTo::extractHrisEmployeeId($employee)
                        : null;

                    if ($reportsToHrisEmployeeId === $user->reports_to_hris_employee_id) {
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

        if ($stats['failed'] > 0) {
            $message .= ", {$stats['failed']} skipped because PHREMS/HRIS was unavailable";
        }

        return back()->with('success', $message.'.');
    }
}
