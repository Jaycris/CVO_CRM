<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->json('permissions')->nullable()->after('description');
        });

        $defaults = [
            'Admin' => [
                'view_all_leads',
                'view_my_leads',
                'view_verification_queue',
                'view_unassigned_leads',
                'view_returned_leads',
                'view_archived_leads',
                'view_assigned_leads',
                'view_sales',
                'create_leads',
                'edit_leads',
                'delete_leads',
                'archive_leads',
                'assign_leads',
                'move_sales_stage',
                'return_leads',
                'view_reports',
                'manage_users',
                'manage_roles_permissions',
            ],
            'Lead Miner' => [
                'view_my_leads',
                'view_returned_leads',
                'view_archived_leads',
                'create_leads',
                'edit_leads',
                'archive_leads',
                'assign_leads',
            ],
            'Branding Specialist' => [
                'view_assigned_leads',
                'view_sales',
                'move_sales_stage',
                'return_leads',
            ],
            'Sales Director' => [
                'view_assigned_leads',
                'view_sales',
                'move_sales_stage',
                'return_leads',
                'view_reports',
            ],
            'Team Leader' => [
                'view_assigned_leads',
                'view_sales',
                'move_sales_stage',
                'return_leads',
            ],
            'Operation Manager' => [
                'view_assigned_leads',
                'view_sales',
                'move_sales_stage',
                'return_leads',
                'view_reports',
            ],
            'Finance Officer' => [
                'view_reports',
            ],
        ];

        DB::table('roles')
            ->orderBy('id')
            ->get()
            ->each(function ($role) use ($defaults) {
                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode($defaults[$role->name] ?? []),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('permissions');
        });
    }
};
