<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'view_assigned_leads_monitor'],
            [
                'group' => 'Lead Pages',
                'label' => 'View Assigned Leads Monitor',
                'description' => 'Can open the Assigned Leads monitor table and see leads already assigned to Sales users.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'view_assigned_leads_monitor')->value('id');
        $legacyPermissionId = DB::table('permissions')->where('key', 'view_assigned_leads')->value('id');

        if (! $permissionId) {
            return;
        }

        $roleIds = DB::table('roles')
            ->when($legacyPermissionId, function ($query) use ($legacyPermissionId) {
                $query->where(function ($query) use ($legacyPermissionId) {
                    $query->where('name', 'Admin')
                        ->orWhere(function ($query) use ($legacyPermissionId) {
                            $query->where('department', '!=', 'Sales')
                                ->whereExists(function ($query) use ($legacyPermissionId) {
                                    $query->selectRaw('1')
                                        ->from('permission_role')
                                        ->whereColumn('permission_role.role_id', 'roles.id')
                                        ->where('permission_role.permission_id', $legacyPermissionId);
                                });
                        });
                });
            }, fn ($query) => $query->where('name', 'Admin'))
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                [
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'view_assigned_leads_monitor')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
