<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'view_finance_clients' => ['Finance Pages', 'View Finance Clients', 'Can view sold clients, refunds, and disputes.'],
            'view_contract_records' => ['Finance Pages', 'View Contract Records', 'Can view clients sent for contract and signed contracts.'],
            'manage_contract_records' => ['Finance Actions', 'Manage Contract Records', 'Can mark contracts as sent or signed.'],
        ];

        foreach ($permissions as $key => [$group, $label, $description]) {
            DB::table('permissions')->insertOrIgnore([
                'key' => $key,
                'group' => $group,
                'label' => $label,
                'description' => $description,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_keys($permissions))
            ->pluck('id', 'key');

        DB::table('roles')
            ->whereIn('name', ['Admin', 'Finance Officer'])
            ->get()
            ->each(function ($role) use ($permissionIds, $permissions) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'permission_id' => $permissionId,
                        'role_id' => $role->id,
                    ]);
                }

                $keys = json_decode($role->permissions ?? '[]', true);
                $keys = is_array($keys) ? $keys : [];

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique([
                            ...$keys,
                            ...array_keys($permissions),
                        ]))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['view_finance_clients', 'view_contract_records', 'manage_contract_records'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
