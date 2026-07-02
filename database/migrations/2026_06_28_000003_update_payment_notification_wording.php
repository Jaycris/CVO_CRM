<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('notifications')
            ->where('type', 'App\\Notifications\\PaymentRefundNotification')
            ->orderBy('id')
            ->get(['id', 'data'])
            ->each(function ($notification) {
                $data = json_decode($notification->data, true) ?: [];

                if (($data['title'] ?? null) === 'Payment marked as refund') {
                    $data['title'] = 'Client Refund';
                    $data['message'] = 'Finance recorded this client as refunded.';

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($data)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('notifications')
            ->where('type', 'App\\Notifications\\PaymentRefundNotification')
            ->orderBy('id')
            ->get(['id', 'data'])
            ->each(function ($notification) {
                $data = json_decode($notification->data, true) ?: [];

                if (($data['title'] ?? null) === 'Client Refund') {
                    $data['title'] = 'Payment marked as refund';
                    $data['message'] = 'Finance marked one of your clients as refund.';

                    DB::table('notifications')
                        ->where('id', $notification->id)
                        ->update(['data' => json_encode($data)]);
                }
            });
    }
};
