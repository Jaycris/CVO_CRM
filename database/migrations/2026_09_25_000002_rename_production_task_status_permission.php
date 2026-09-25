<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'update_production_project_status')
            ->update([
                'label' => 'Update Production Task Status',
                'description' => 'Can update production task status and task notes.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'update_production_project_status')
            ->update([
                'label' => 'Update Production Project Status',
                'description' => 'Can update production project status and fulfillment notes.',
                'updated_at' => now(),
            ]);
    }
};
