<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $permissionKey = 'view_all_author_balances';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => $this->permissionKey],
            [
                'group' => 'Finance Pages',
                'label' => 'View All Author Balances',
                'description' => 'Can see every author balance on the Author Balances page. If unchecked, users only see their own author balances.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $permissionId = DB::table('permissions')->where('key', $this->permissionKey)->value('id');

        DB::table('roles')
            ->whereIn('name', ['Admin', 'Finance Officer'])
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
