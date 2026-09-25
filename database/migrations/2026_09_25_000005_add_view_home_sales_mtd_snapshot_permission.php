<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permissionKey = 'view_home_sales_mtd_snapshot';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => $this->permissionKey],
            [
                'group' => 'Reports',
                'label' => 'Show Home Sales MTD Snapshot',
                'description' => 'Check this to show Sales Brand MTD Snapshot cards on the Home page.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('key', $this->permissionKey)->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', [
                'Admin',
                'Branding Specialist',
                'Team Leader',
                'Trainee',
                'Sales Director',
                'Operation Manager',
            ])
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
        $permissionId = DB::table('permissions')->where('key', $this->permissionKey)->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('key', $this->permissionKey)->delete();
    }
};
