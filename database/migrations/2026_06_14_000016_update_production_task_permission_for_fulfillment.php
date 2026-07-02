<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'view_my_production_tasks'],
            [
                'group' => 'Production Pages',
                'label' => 'View My New and Completed Tasks',
                'description' => 'Can open My New Task and My Complete Tasks for production work assigned to the authenticated user.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'view_my_production_tasks')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->where('department', 'Production')
            ->where('name', 'Fulfillment Officer')
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
                        'permissions' => json_encode(array_values(array_unique([...$keys, 'view_my_production_tasks']))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('key', 'view_my_production_tasks')
            ->update([
                'label' => 'View My Production Tasks',
                'description' => 'Can see production projects assigned to the authenticated user.',
                'updated_at' => now(),
            ]);
    }
};
