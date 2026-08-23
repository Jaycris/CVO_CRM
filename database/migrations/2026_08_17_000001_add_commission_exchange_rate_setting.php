<?php

use App\Models\AppSetting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        AppSetting::set('commission_exchange_rate', AppSetting::DEFAULT_COMMISSION_EXCHANGE_RATE);
        AppSetting::set('card_payment_hold_percent', AppSetting::DEFAULT_CARD_PAYMENT_HOLD_PERCENT);
    }

    public function down(): void
    {
        AppSetting::whereIn('key', [
            'commission_exchange_rate',
            'card_payment_hold_percent',
        ])->delete();
    }
};
