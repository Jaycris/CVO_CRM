<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommissionSettingController extends Controller
{
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        return view('admin.commission-settings.edit', [
            'frankiePercent' => (float) AppSetting::get('frankie_commission_percent', 50),
            'exchangeRate' => AppSetting::commissionExchangeRate(),
            'cardHoldPercent' => AppSetting::cardPaymentHoldPercent(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->name === 'Admin', 403);

        $validated = $request->validate([
            'frankie_commission_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'commission_exchange_rate' => ['required', 'numeric', 'min:0.01'],
            'card_payment_hold_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        AppSetting::set('frankie_commission_percent', number_format((float) $validated['frankie_commission_percent'], 2, '.', ''));
        AppSetting::set('commission_exchange_rate', number_format((float) $validated['commission_exchange_rate'], 4, '.', ''));
        AppSetting::set('card_payment_hold_percent', number_format((float) $validated['card_payment_hold_percent'], 2, '.', ''));

        return back()->with('success', 'Commission settings updated successfully.');
    }
}
