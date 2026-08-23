<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        return view('admin.system-settings.edit', [
            'recordsPerPage' => AppSetting::recordsPerPage(),
            'leadsSalesRecordsPerPage' => AppSetting::leadsSalesRecordsPerPage(),
            'recordsPerPageOptions' => [10, 25, 50, 100],
            'hrisApiToken' => AppSetting::hrisApiToken(),
            'hrisBaseUrl' => AppSetting::hrisBaseUrl(),
            'hrisCrmLookupToken' => AppSetting::hrisCrmLookupToken(),
            'commissionSlipApiUrl' => route('api.hris.commission-slip'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        $validated = $request->validate([
            'records_per_page' => ['required', 'integer', 'in:10,25,50,100'],
            'leads_sales_records_per_page' => ['required', 'integer', 'in:10,25,50,100'],
            'hris_base_url' => ['nullable', 'url', 'max:255'],
            'hris_crm_lookup_token' => ['nullable', 'string', 'max:255'],
        ]);

        AppSetting::set('records_per_page', $validated['records_per_page']);
        AppSetting::set('leads_sales_records_per_page', $validated['leads_sales_records_per_page']);
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
}
