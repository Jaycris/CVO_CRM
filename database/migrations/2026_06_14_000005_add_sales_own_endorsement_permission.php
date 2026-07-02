<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'view_own_sales_endorsements'],
            [
                'group' => 'Sales Pages',
                'label' => 'View Sales Endorsement',
                'description' => 'Can open Sales Endorsement and see only endorsements submitted by the user.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('permissions')->updateOrInsert(
            ['key' => 'view_all_sales_endorsements'],
            [
                'group' => 'Sales Pages',
                'label' => 'View All Sales Endorsements',
                'description' => 'Can open Sales Endorsement and see all submitted endorsements from Sales users.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('permissions')
            ->where('key', 'view_sales_endorsement_form')
            ->update([
                'group' => 'Finance Pages',
                'label' => 'View Sales Endorsement',
                'description' => 'Can open Sales Endorsement and see all submitted endorsements for Finance review.',
                'updated_at' => now(),
            ]);

        $salesPermissionId = DB::table('permissions')->where('key', 'view_own_sales_endorsements')->value('id');
        $salesAllPermissionId = DB::table('permissions')->where('key', 'view_all_sales_endorsements')->value('id');
        $financePermissionId = DB::table('permissions')->where('key', 'view_sales_endorsement_form')->value('id');

        if ($salesPermissionId && $financePermissionId) {
            DB::table('roles')
                ->where('department', 'Sales')
                ->get()
                ->each(function ($role) use ($salesPermissionId, $financePermissionId) {
                    $hasFinanceEndorsementAccess = DB::table('permission_role')
                        ->where('role_id', $role->id)
                        ->where('permission_id', $financePermissionId)
                        ->exists();

                    if ($hasFinanceEndorsementAccess) {
                        DB::table('permission_role')->updateOrInsert([
                            'role_id' => $role->id,
                            'permission_id' => $salesPermissionId,
                        ]);

                        DB::table('permission_role')
                            ->where('role_id', $role->id)
                            ->where('permission_id', $financePermissionId)
                            ->delete();

                        $keys = json_decode($role->permissions ?? '[]', true);
                        $keys = is_array($keys) ? $keys : [];
                        $keys = array_values(array_unique([
                            ...array_diff($keys, ['view_sales_endorsement_form']),
                            'view_own_sales_endorsements',
                        ]));

                        DB::table('roles')
                            ->where('id', $role->id)
                            ->update([
                                'permissions' => json_encode($keys),
                                'updated_at' => now(),
                            ]);
                    }
                });
        }

        if ($salesPermissionId && $salesAllPermissionId) {
            DB::table('roles')
                ->where('name', 'Admin')
                ->pluck('id')
                ->each(function ($roleId) use ($salesPermissionId, $salesAllPermissionId) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $salesPermissionId,
                    ]);

                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $salesAllPermissionId,
                    ]);
                });
        }
    }

    public function down(): void
    {
        $salesPermissionId = DB::table('permissions')->where('key', 'view_own_sales_endorsements')->value('id');
        $salesAllPermissionId = DB::table('permissions')->where('key', 'view_all_sales_endorsements')->value('id');
        $financePermissionId = DB::table('permissions')->where('key', 'view_sales_endorsement_form')->value('id');

        if ($salesPermissionId && $financePermissionId) {
            DB::table('roles')
                ->where('department', 'Sales')
                ->get()
                ->each(function ($role) use ($salesPermissionId, $financePermissionId) {
                    $hasSalesEndorsementAccess = DB::table('permission_role')
                        ->where('role_id', $role->id)
                        ->where('permission_id', $salesPermissionId)
                        ->exists();

                    if ($hasSalesEndorsementAccess) {
                        DB::table('permission_role')->updateOrInsert([
                            'role_id' => $role->id,
                            'permission_id' => $financePermissionId,
                        ]);
                    }

                    $keys = json_decode($role->permissions ?? '[]', true);
                    $keys = is_array($keys) ? $keys : [];

                    if (in_array('view_own_sales_endorsements', $keys, true)) {
                        $keys = array_values(array_unique([
                            ...array_diff($keys, ['view_own_sales_endorsements']),
                            'view_sales_endorsement_form',
                        ]));

                        DB::table('roles')
                            ->where('id', $role->id)
                            ->update([
                                'permissions' => json_encode($keys),
                                'updated_at' => now(),
                            ]);
                    }
                });
        }

        foreach (array_filter([$salesPermissionId, $salesAllPermissionId]) as $permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('permissions')
            ->where('key', 'view_sales_endorsement_form')
            ->update([
                'group' => 'Finance Pages',
                'label' => 'View Sales Endorsement',
                'description' => 'Can open the Sales Endorsement page under Finance.',
                'updated_at' => now(),
            ]);
    }
};
