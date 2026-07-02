<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('leads')
            ->whereIn('lead_generation_stage', ['ready_to_assign', 'ready_to_return'])
            ->whereNull('verified_at')
            ->update(['lead_generation_stage' => null]);
    }

    public function down(): void
    {
        // Data cleanup only. Nothing to restore safely.
    }
};
