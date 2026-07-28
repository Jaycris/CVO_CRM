<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SystemSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        return view('admin.system-settings.edit', [
            'recordsPerPage' => AppSetting::recordsPerPage(),
            'recordsPerPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        $validated = $request->validate([
            'records_per_page' => ['required', 'integer', 'in:10,25,50,100'],
        ]);

        AppSetting::set('records_per_page', $validated['records_per_page']);

        return back()->with('success', 'System settings updated successfully.');
    }
}
