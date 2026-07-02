<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view_sold_mined_leads' => [
                'group' => 'Lead Pages',
                'label' => 'View Sold Mined Leads',
                'description' => 'Can view leads mined by the user that became successful sales and receive grouped sale-credit notifications.',
                'roles' => ['Admin', 'Lead Miner'],
            ],
            'view_verified_sold_leads' => [
                'group' => 'Lead Pages',
                'label' => 'View Verified Sold Leads',
                'description' => 'Can view leads verified by the user that became successful sales and receive grouped sale-credit notifications.',
                'roles' => ['Admin', 'Verifier'],
            ],
        ];

        foreach ($permissions as $key => $details) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => $details['group'],
                    'label' => $details['label'],
                    'description' => $details['description'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            $permissionId = DB::table('permissions')->where('key', $key)->value('id');

            if (! $permissionId) {
                continue;
            }

            $roleIds = DB::table('roles')
                ->whereIn('name', $details['roles'])
                ->pluck('id');

            foreach ($roleIds as $roleId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['view_sold_mined_leads', 'view_verified_sold_leads'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
