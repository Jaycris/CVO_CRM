<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'verify_leads'],
            [
            'group' => 'Lead Actions',
            'label' => 'Verify Leads',
            'description' => 'Can verify leads and save verification scores.',
            'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('key', 'verify_leads')
            ->value('id');

        DB::table('roles')
            ->where('name', 'Admin')
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
        $permissionId = DB::table('permissions')->where('key', 'verify_leads')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')->where('key', 'verify_leads')->delete();
    }
};
