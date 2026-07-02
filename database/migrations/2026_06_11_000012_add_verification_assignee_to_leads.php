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
            $table->foreignId('verification_assigned_to')
                ->nullable()
                ->after('lead_generation_stage')
                ->constrained('users')
                ->nullOnDelete();
        });

        $verifierIds = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.department', 'Lead Generation')
            ->whereJsonContains('roles.permissions', 'view_verification_queue')
            ->whereJsonContains('roles.permissions', 'verify_leads')
            ->orderBy('users.id')
            ->pluck('users.id');

        if ($verifierIds->isEmpty()) {
            return;
        }

        $loads = $verifierIds->mapWithKeys(fn ($id) => [$id => 0]);
        $leadIds = DB::table('leads')
            ->where('lead_generation_stage', 'verification_queue')
            ->whereNull('verification_assigned_to')
            ->orderBy('id')
            ->pluck('id');

        foreach ($leadIds as $leadId) {
            $lowestLoad = $loads->min();
            $verifierId = $loads
                ->filter(fn ($load) => $load === $lowestLoad)
                ->keys()
                ->random();

            DB::table('leads')
                ->where('id', $leadId)
                ->update(['verification_assigned_to' => $verifierId]);

            $loads[$verifierId] = $loads[$verifierId] + 1;
        }
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['verification_assigned_to']);
            $table->dropColumn('verification_assigned_to');
        });
    }
};
