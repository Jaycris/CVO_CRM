<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'send_leads_to_verification' => [
                'Lead Actions',
                'Send Leads to Verification',
                'Can send mined or repaired leads to the Verification Queue.',
            ],
            'move_verified_leads_to_ready' => [
                'Lead Actions',
                'Move Verified Leads to Ready Queue',
                'Can move verified leads to Ready to Assign or Ready to Return.',
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

        DB::table('permissions')
            ->where('key', 'view_verification_queue')
            ->update([
                'description' => 'Can see leads sent by Lead Miners for verification or re-verification.',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'view_unassigned_leads')
            ->update([
                'description' => 'Can see verified leads that are ready to assign to Sales.',
                'updated_at' => now(),
            ]);

        DB::table('permissions')
            ->where('key', 'assign_leads')
            ->update([
                'description' => 'Can assign ready leads to Sales department users.',
                'updated_at' => now(),
            ]);

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_keys($permissions))
            ->pluck('id', 'key');

        $defaultRolePermissions = [
            'Admin' => array_keys($permissions),
            'Lead Miner' => ['send_leads_to_verification'],
            'Verifier' => ['move_verified_leads_to_ready'],
        ];

        foreach ($defaultRolePermissions as $roleName => $permissionKeys) {
            DB::table('roles')
                ->where('name', $roleName)
                ->pluck('id')
                ->each(function ($roleId) use ($permissionKeys, $permissionIds) {
                    foreach ($permissionKeys as $permissionKey) {
                        if (! isset($permissionIds[$permissionKey])) {
                            continue;
                        }

                        DB::table('permission_role')->updateOrInsert([
                            'role_id' => $roleId,
                            'permission_id' => $permissionIds[$permissionKey],
                        ]);
                    }

                    $keys = json_decode(DB::table('roles')->where('id', $roleId)->value('permissions') ?? '[]', true);

                    DB::table('roles')
                        ->where('id', $roleId)
                        ->update([
                            'permissions' => json_encode(array_values(array_unique([...$keys, ...$permissionKeys]))),
                            'updated_at' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['send_leads_to_verification', 'move_verified_leads_to_ready'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }
};
