<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('key', 'view_sold_mined_leads')
            ->update([
                'label' => 'View Sold Leads',
                'description' => 'Can view successful sold leads with Lead Miner and Verifier stamps.',
                'group' => 'Reports',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'view_verified_sold_leads')
            ->update([
                'label' => 'View Verified Sold Leads',
                'description' => 'Verifier-only report for successful sales credited to verified leads.',
                'group' => 'Reports',
                'updated_at' => now(),
            ]);

        $verifiedPermissionId = DB::table('permissions')
            ->where('key', 'view_verified_sold_leads')
            ->value('id');

        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($verifiedPermissionId && $adminRoleId) {
            DB::table('permission_role')
                ->where('permission_id', $verifiedPermissionId)
                ->where('role_id', $adminRoleId)
                ->delete();
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'view_sold_mined_leads')
            ->update([
                'label' => 'View Sold Mined Leads',
                'description' => 'Can view successful sales credited to mined leads.',
                'group' => 'Reports',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'view_verified_sold_leads')
            ->update([
                'label' => 'View Verified Sold Leads',
                'description' => 'Can view successful sales credited to verified leads.',
                'group' => 'Reports',
                'updated_at' => now(),
            ]);

        $verifiedPermissionId = DB::table('permissions')
            ->where('key', 'view_verified_sold_leads')
            ->value('id');

        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($verifiedPermissionId && $adminRoleId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $verifiedPermissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }
};
