<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->updateOrInsert(
            ['key' => 'self_mine_work_leads'],
            [
                'group' => 'Lead Actions',
                'label' => 'Self Mine and Work Leads',
                'description' => 'Can add leads as a Sales user and immediately assign those leads to themselves for direct follow-up.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'self_mine_work_leads')->value('id');

        if ($permissionId && Schema::hasTable('roles') && Schema::hasTable('permission_role')) {
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
    }

    public function down(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::table('permissions')->where('key', 'self_mine_work_leads')->delete();
    }
};
