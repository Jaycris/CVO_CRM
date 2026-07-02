<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'delete_sales_endorsements' => [
                'Finance Actions',
                'Delete Sales Endorsements',
                'Can delete one or multiple sales endorsement records.',
            ],
        ];

        foreach ($permissions as $key => [$group, $label, $description]) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $key],
                [
                    'group' => $group,
                    'label' => $label,
                    'description' => $description,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        DB::table('permissions')
            ->where('key', 'manage_payment_records')
            ->update([
                'description' => 'Can update or delete payment records and finance client records.',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'manage_contract_records')
            ->update([
                'description' => 'Can mark contracts as sent or signed, and delete contract records.',
                'updated_at' => now(),
            ]);

        $adminRoleIds = DB::table('roles')
            ->where('name', 'Admin')
            ->pluck('id');

        $permissionId = DB::table('permissions')
            ->where('key', 'delete_sales_endorsements')
            ->value('id');

        foreach ($adminRoleIds as $roleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $roleId,
            ]);

            $keys = json_decode(DB::table('roles')->where('id', $roleId)->value('permissions') ?? '[]', true);
            $keys = is_array($keys) ? $keys : [];

            DB::table('roles')
                ->where('id', $roleId)
                ->update([
                    'permissions' => json_encode(array_values(array_unique([...$keys, 'delete_sales_endorsements']))),
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'delete_sales_endorsements')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('permissions')
            ->where('key', 'manage_payment_records')
            ->update([
                'description' => 'Can update payment method, sold date, and payment status.',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'manage_contract_records')
            ->update([
                'description' => 'Can mark contracts as sent or signed.',
                'updated_at' => now(),
            ]);
    }
};
