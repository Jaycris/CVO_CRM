<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            $table->foreignId('fulfillment_officer_id')
                ->nullable()
                ->after('tracker_type')
                ->constrained('users')
                ->nullOnDelete();
        });

        DB::table('production_projects')
            ->join('users', 'production_projects.assigned_to', '=', 'users.id')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.name', 'Fulfillment Officer')
            ->update([
                'production_projects.fulfillment_officer_id' => DB::raw('production_projects.assigned_to'),
                'production_projects.assigned_to' => null,
            ]);
    }

    public function down(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            $table->dropForeign(['fulfillment_officer_id']);
            $table->dropColumn('fulfillment_officer_id');
        });
    }
};
