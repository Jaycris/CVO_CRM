<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'view_client_project_progress')
            ->update([
                'description' => 'Can see Fulfillment Tracker progress for the authenticated sales agent clients.',
            ]);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'view_client_project_progress')
            ->update([
                'description' => 'Can see production progress for the authenticated sales agent clients.',
            ]);
    }
};
