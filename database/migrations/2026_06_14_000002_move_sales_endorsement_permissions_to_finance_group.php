<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'view_sales_endorsement_form')
            ->update([
                'group' => 'Finance Pages',
                'label' => 'View Sales Endorsement',
                'description' => 'Can open the Sales Endorsement page under Finance.',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'submit_sales_endorsement')
            ->update([
                'group' => 'Finance Actions',
                'label' => 'Submit Sales Endorsement',
                'description' => 'Can create a sales endorsement record for Finance review.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'view_sales_endorsement_form')
            ->update([
                'group' => 'Sales Pages',
                'label' => 'View Sales Endorsement Form',
                'description' => 'Can open the sales endorsement form page.',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'submit_sales_endorsement')
            ->update([
                'group' => 'Sales Actions',
                'label' => 'Can Submit a Form',
                'description' => 'Can submit sales endorsement forms.',
                'updated_at' => now(),
            ]);
    }
};
