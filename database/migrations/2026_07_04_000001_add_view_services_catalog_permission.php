<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->insertOrIgnore([
            'key' => 'view_services_catalog',
            'group' => 'General Pages',
            'label' => 'View Services Catalog',
            'description' => 'Can browse service offers, prices, descriptions, and inclusions.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('permissions')
            ->where('key', 'view_services_catalog')
            ->update([
                'group' => 'General Pages',
                'label' => 'View Services Catalog',
                'description' => 'Can browse service offers, prices, descriptions, and inclusions.',
                'updated_at' => now(),
            ]);

        $permissionId = DB::table('permissions')->where('key', 'view_services_catalog')->value('id');

        if (! $permissionId) {
            return;
        }

        $defaultRoleNames = [
            'Admin',
            'Branding Specialist',
            'Team Leader',
            'Sales Director',
            'Operation Manager',
            'Trainee',
        ];

        DB::table('roles')
            ->whereIn('name', $defaultRoleNames)
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'view_services_catalog')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
