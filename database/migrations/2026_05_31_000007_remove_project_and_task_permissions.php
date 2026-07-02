<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['view_projects', 'view_my_tasks'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();
    }

    public function down(): void
    {
        $permissions = [
            'view_projects' => ['Production Pages', 'View Projects', 'Can open the project tracker when the production flow is added.'],
            'view_my_tasks' => ['Production Pages', 'View My Task', 'Can open assigned task work when the production flow is added.'],
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
    }
};
