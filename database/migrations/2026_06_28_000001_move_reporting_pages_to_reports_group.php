<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view_sold_mined_leads' => [
                'label' => 'View Sold Mined Leads',
                'description' => 'Can view successful sales credited to mined leads.',
                'roles' => ['Admin', 'Lead Miner'],
            ],
            'view_verified_sold_leads' => [
                'label' => 'View Verified Sold Leads',
                'description' => 'Can view successful sales credited to verified leads.',
                'roles' => ['Admin', 'Verifier'],
            ],
            'view_sales_activity' => [
                'label' => 'View Sales Activity',
                'description' => 'Can view automatic successful-payment activity used for reports.',
                'roles' => ['Admin', 'Finance Officer'],
            ],
            'view_production_reports' => [
                'label' => 'View Production Reports',
                'description' => 'Can view production project, fulfillment, and task workload reports.',
                'roles' => ['Admin', 'Operation Manager', 'Fulfillment Officer'],
            ],
        ];

        foreach ($permissions as $key => $details) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'Reports',
                    'label' => $details['label'],
                    'description' => $details['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            $permissionId = DB::table('permissions')->where('key', $key)->value('id');

            if (! $permissionId) {
                continue;
            }

            DB::table('roles')
                ->whereIn('name', $details['roles'])
                ->pluck('id')
                ->each(function ($roleId) use ($permissionId) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                });
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('key', ['view_sold_mined_leads', 'view_verified_sold_leads'])
            ->update([
                'group' => 'Lead Pages',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'view_sales_activity')
            ->update([
                'group' => 'Finance Pages',
                'updated_at' => now(),
            ]);

        $productionReportPermissionId = DB::table('permissions')
            ->where('key', 'view_production_reports')
            ->value('id');

        if ($productionReportPermissionId) {
            DB::table('permission_role')->where('permission_id', $productionReportPermissionId)->delete();
            DB::table('permission_user')->where('permission_id', $productionReportPermissionId)->delete();
            DB::table('permissions')->where('id', $productionReportPermissionId)->delete();
        }
    }
};
