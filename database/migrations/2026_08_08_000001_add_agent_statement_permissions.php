<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissionKeys = [
        'view_agent_statements',
        'view_all_agent_statements',
    ];

    public function up(): void
    {
        $permissions = [
            'view_agent_statements' => [
                'Reports',
                'View Agent Statements',
                'Can view their own monthly sales and commission statement.',
            ],
            'view_all_agent_statements' => [
                'Reports',
                'View All Agent Statements',
                'Can view monthly sales and commission statements for all agents allowed by brand access.',
            ],
        ];

        foreach ($permissions as $key => [$group, $label, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => $group,
                    'label' => $label,
                    'description' => $description,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $ownPermissionId = DB::table('permissions')->where('key', 'view_agent_statements')->value('id');
        $allPermissionId = DB::table('permissions')->where('key', 'view_all_agent_statements')->value('id');

        $roleGrants = [
            'Admin' => [$ownPermissionId, $allPermissionId],
            'Branding Specialist' => [$ownPermissionId],
            'Trainee' => [$ownPermissionId],
            'Team Leader' => [$ownPermissionId, $allPermissionId],
            'Sales Director' => [$ownPermissionId, $allPermissionId],
            'Operation Manager' => [$ownPermissionId, $allPermissionId],
        ];

        foreach ($roleGrants as $roleName => $permissionIds) {
            DB::table('roles')
                ->where('name', $roleName)
                ->pluck('id')
                ->each(function ($roleId) use ($permissionIds) {
                    foreach (array_filter($permissionIds) as $permissionId) {
                        DB::table('permission_role')->insertOrIgnore([
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ]);
                    }
                });

            DB::table('roles')
                ->where('name', $roleName)
                ->get()
                ->each(function ($role) use ($permissionIds) {
                    $keys = json_decode($role->permissions ?? '[]', true) ?: [];
                    $grantKeys = DB::table('permissions')->whereIn('id', array_filter($permissionIds))->pluck('key')->all();

                    DB::table('roles')
                        ->where('id', $role->id)
                        ->update([
                            'permissions' => json_encode(array_values(array_unique([...$keys, ...$grantKeys]))),
                            'updated_at' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', $this->permissionKeys)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();

        DB::table('roles')->get()->each(function ($role) {
            $keys = json_decode($role->permissions ?? '[]', true) ?: [];
            $keys = array_values(array_diff($keys, $this->permissionKeys));

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode($keys),
                    'updated_at' => now(),
                ]);
        });

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
