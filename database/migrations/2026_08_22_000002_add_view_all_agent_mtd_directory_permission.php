<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permissionKey = 'view_all_agent_mtd_directory';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => $this->permissionKey],
            [
                'group' => 'Reports',
                'label' => 'View All Agent MTD Directory',
                'description' => 'Can see every agent row in the Sales Dashboard MTD Agent MTD Directory.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', $this->permissionKey)->value('id');

        DB::table('roles')
            ->whereIn('slug', ['admin', 'sales-director', 'operation-manager'])
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId) {
                DB::table('permission_role')->insertOrIgnore([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
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
