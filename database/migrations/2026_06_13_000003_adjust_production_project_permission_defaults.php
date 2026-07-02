<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['view_production_projects', 'manage_production_projects', 'view_client_project_progress'])
            ->pluck('id', 'key');

        DB::table('roles')
            ->where('department', 'Sales')
            ->where('name', 'Operation Manager')
            ->get()
            ->each(function ($role) use ($permissionIds) {
                DB::table('permission_role')
                    ->where('role_id', $role->id)
                    ->whereIn('permission_id', [
                        $permissionIds['view_production_projects'] ?? 0,
                        $permissionIds['manage_production_projects'] ?? 0,
                    ])
                    ->delete();

                if (isset($permissionIds['view_client_project_progress'])) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionIds['view_client_project_progress'],
                    ]);
                }

                $keys = json_decode($role->permissions ?? '[]', true);
                $keys = is_array($keys) ? $keys : [];
                $keys = array_values(array_diff($keys, ['view_production_projects', 'manage_production_projects']));
                $keys[] = 'view_client_project_progress';

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique($keys))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        // Permission cleanup only. No safe rollback needed.
    }
};
