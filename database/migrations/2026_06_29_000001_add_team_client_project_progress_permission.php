<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'view_team_client_project_progress'],
            [
                'group' => 'Sales Pages',
                'label' => 'View Team Client Project Progress',
                'description' => 'Can see Fulfillment Tracker progress for clients owned by Sales agents in the user’s team.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('key', 'view_team_client_project_progress')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->where('department', 'Sales')
            ->whereIn('name', ['Team Leader', 'Sales Director', 'Operation Manager'])
            ->pluck('id')
            ->push(DB::table('roles')->where('name', 'Admin')->value('id'))
            ->filter()
            ->unique()
            ->each(function ($roleId) use ($permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'view_team_client_project_progress')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
        }

        DB::table('permissions')
            ->where('key', 'view_team_client_project_progress')
            ->delete();
    }
};
