<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'delete_payment_records')
            ->update([
                'label' => 'Delete Finance Records',
                'description' => 'Can delete Payment Records, Sold Clients, Refunds & Disputes, and Contracts data.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'delete_payment_records')
            ->update([
                'label' => 'Delete Payment Records',
                'description' => 'Can delete one or multiple payment records.',
                'updated_at' => now(),
            ]);
    }
};
