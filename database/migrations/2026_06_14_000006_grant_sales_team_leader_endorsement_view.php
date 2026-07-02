<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'view_all_sales_endorsements')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->where('department', 'Sales')
            ->where('name', 'Team Leader')
            ->get()
            ->each(function ($role) use ($permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $role->id,
                    'permission_id' => $permissionId,
                ]);

                $keys = json_decode($role->permissions ?? '[]', true);
                $keys = is_array($keys) ? $keys : [];

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique([
                            ...$keys,
                            'view_all_sales_endorsements',
                        ]))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'view_all_sales_endorsements')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->where('department', 'Sales')
            ->where('name', 'Team Leader')
            ->get()
            ->each(function ($role) use ($permissionId) {
                DB::table('permission_role')
                    ->where('role_id', $role->id)
                    ->where('permission_id', $permissionId)
                    ->delete();

                $keys = json_decode($role->permissions ?? '[]', true);
                $keys = is_array($keys) ? $keys : [];

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_diff($keys, ['view_all_sales_endorsements']))),
                        'updated_at' => now(),
                    ]);
            });
    }
};
