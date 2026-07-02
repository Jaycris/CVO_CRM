<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view_team_leads' => [
                'Sales Pages',
                'View Team Leads',
                'Can monitor assigned Sales department leads across the team.',
            ],
            'reassign_team_leads' => [
                'Sales Actions',
                'Reassign Team Leads',
                'Can reassign active assigned Sales leads to another Sales department user.',
            ],
            'unassign_team_leads' => [
                'Sales Actions',
                'Unassign Team Leads',
                'Can move active assigned Sales leads back to the ready-to-assign queue.',
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

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_keys($permissions))
            ->pluck('id', 'key');

        DB::table('roles')
            ->where(function ($query) {
                $query->where('name', 'Admin')
                    ->orWhere(function ($query) {
                        $query->where('department', 'Sales')
                            ->where('name', 'Team Leader');
                    });
            })
            ->get()
            ->each(function ($role) use ($permissionIds) {
                $permissionKeys = $role->name === 'Admin'
                    ? $permissionIds->keys()->all()
                    : ['view_team_leads'];

                foreach ($permissionKeys as $permissionKey) {
                    if (! isset($permissionIds[$permissionKey])) {
                        continue;
                    }

                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionIds[$permissionKey],
                    ]);
                }

                $keys = json_decode($role->permissions ?? '[]', true);
                $keys = is_array($keys) ? $keys : [];

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique([...$keys, ...$permissionKeys]))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        $permissionKeys = ['view_team_leads', 'reassign_team_leads', 'unassign_team_leads'];
        $permissionIds = DB::table('permissions')->whereIn('key', $permissionKeys)->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        DB::table('roles')->get()->each(function ($role) use ($permissionKeys) {
            $keys = json_decode($role->permissions ?? '[]', true);

            if (! is_array($keys)) {
                return;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_diff($keys, $permissionKeys))),
                    'updated_at' => now(),
                ]);
        });
    }
};
