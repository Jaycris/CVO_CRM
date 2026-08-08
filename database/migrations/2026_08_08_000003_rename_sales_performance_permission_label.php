<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'view_sales_performance_mtd')
            ->update([
                'label' => 'View Sales Dashboard MTD',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'view_sales_performance_mtd')
            ->update([
                'label' => 'View Sales Performance MTD',
                'updated_at' => now(),
            ]);
    }
};
