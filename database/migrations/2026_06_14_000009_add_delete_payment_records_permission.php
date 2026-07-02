<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'delete_payment_records'],
            [
                'group' => 'Finance Actions',
                'label' => 'Delete Payment Records',
                'description' => 'Can delete one or multiple payment records.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        DB::table('permissions')
            ->where('key', 'manage_payment_records')
            ->update([
                'description' => 'Can add or update payment method, sold date, and payment status.',
                'updated_at' => now(),
            ]);

        $permissionId = DB::table('permissions')->where('key', 'delete_payment_records')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->whereIn('name', ['Admin', 'Finance Officer'])
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
                        'permissions' => json_encode(array_values(array_unique([...$keys, 'delete_payment_records']))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'delete_payment_records')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('permissions')
            ->where('key', 'manage_payment_records')
            ->update([
                'description' => 'Can update or delete payment records and finance client records.',
                'updated_at' => now(),
            ]);
    }
};
