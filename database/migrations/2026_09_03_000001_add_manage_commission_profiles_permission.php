<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $permissions = [
        'manage_commission_profiles' => [
            'label' => 'Manage Commission Profiles',
            'description' => 'Can create and edit reusable commission schemes from the Commission Profiles page.',
        ],
        'manage_employee_commission_profiles' => [
            'label' => 'Manage Employee Commission Profiles',
            'description' => 'Can open and edit an employee commission profile, including agent target, scheme, markup, threshold, and eligibility.',
        ],
    ];

    public function up(): void
    {
        foreach ($this->permissions as $key => $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => 'Administration',
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('key', array_keys($this->permissions))->pluck('id');

        DB::table('roles')
            ->where('name', 'Admin')
            ->pluck('id')
            ->each(function ($roleId) use ($permissionIds) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            });

        DB::table('roles')
            ->where('name', 'Admin')
            ->get()
            ->each(function ($role) {
                $keys = json_decode($role->permissions ?? '[]', true) ?: [];

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique([...$keys, ...array_keys($this->permissions)]))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('key', array_keys($this->permissions))->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('roles')->get()->each(function ($role) {
            $keys = json_decode($role->permissions ?? '[]', true) ?: [];

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_diff($keys, array_keys($this->permissions)))),
                    'updated_at' => now(),
                ]);
        });

        DB::table('permissions')->whereIn('key', array_keys($this->permissions))->delete();
    }
};
