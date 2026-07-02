<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['key' => 'send_returned_leads_back'],
            [
                'group' => 'Lead Actions',
                'label' => 'Send Returned Leads Back',
                'description' => 'Can send repaired returned leads back to the assigned sales agent.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'send_returned_leads_back')->value('id');

        DB::table('roles')
            ->whereIn('name', ['Admin', 'Lead Miner'])
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);

                $keys = json_decode(DB::table('roles')->where('id', $roleId)->value('permissions') ?? '[]', true);

                DB::table('roles')
                    ->where('id', $roleId)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique([...$keys, 'send_returned_leads_back']))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'send_returned_leads_back')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
