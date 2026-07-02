<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_generation_stage')->nullable()->after('archived_by');
        });

        DB::table('leads')
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->whereNull('assigned_to')
            ->whereNull('returned_at')
            ->whereNotNull('verified_at')
            ->update(['lead_generation_stage' => 'ready_to_assign']);

        DB::table('leads')
            ->whereNull('deleted_at')
            ->whereNull('archived_at')
            ->whereNotNull('returned_at')
            ->whereNotNull('verified_at')
            ->update(['lead_generation_stage' => 'ready_to_return']);
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('lead_generation_stage');
        });
    }
};
