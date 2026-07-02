<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            $table->string('tracker_type')->default('publishing')->after('sales_endorsement_id');
            $table->foreignId('endorsed_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('endorsed_at')->nullable()->after('endorsed_by');
            $table->text('endorsement_notes')->nullable()->after('endorsed_at');
        });

        DB::table('production_projects')
            ->where('status', 'in_production')
            ->update(['status' => 'in_progress']);

        DB::table('production_projects')
            ->where('status', 'completed')
            ->update(['status' => 'fulfilled']);

        DB::table('production_projects')
            ->whereNull('endorsed_at')
            ->update(['endorsed_at' => now()]);

        $permissions = [
            'endorse_projects_to_production' => [
                'Finance Actions',
                'Endorse Projects to Production',
                'Can endorse paid and signed contracts to Production fulfillment trackers.',
            ],
            'view_all_fulfillment_trackers' => [
                'Production Pages',
                'View All Fulfillment Trackers',
                'Can see Publishing, Marketing, and Events fulfillment trackers.',
            ],
            'view_publishing_tracker' => [
                'Production Pages',
                'View Publishing Tracker',
                'Can see projects endorsed to the Publishing tracker.',
            ],
            'view_marketing_tracker' => [
                'Production Pages',
                'View Marketing Tracker',
                'Can see projects endorsed to the Marketing tracker.',
            ],
            'view_events_tracker' => [
                'Production Pages',
                'View Events Tracker',
                'Can see projects endorsed to the Events tracker.',
            ],
            'view_my_production_tasks' => [
                'Production Pages',
                'View My Production Tasks',
                'Can see production projects assigned to the authenticated user.',
            ],
            'assign_production_projects' => [
                'Production Actions',
                'Assign Production Projects',
                'Can assign endorsed projects to production team members.',
            ],
            'update_production_project_status' => [
                'Production Actions',
                'Update Production Project Status',
                'Can update production project status and fulfillment notes.',
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

        DB::table('roles')->get()->each(function ($role) use ($permissionIds) {
            $permissionKeys = match (true) {
                $role->name === 'Admin' => $permissionIds->keys()->all(),
                $role->name === 'Finance Officer' => ['endorse_projects_to_production'],
                $role->department === 'Production' && in_array($role->name, ['Operation Manager', 'Fulfillment Officer'], true) => [
                    'view_production_projects',
                    'manage_production_projects',
                    'view_all_fulfillment_trackers',
                    'view_publishing_tracker',
                    'view_marketing_tracker',
                    'view_events_tracker',
                    'assign_production_projects',
                    'update_production_project_status',
                ],
                $role->department === 'Production' && in_array($role->name, ['Web Designer', 'Video Editor', 'Writer', 'Graphic Designer'], true) => [
                    'view_my_production_tasks',
                    'update_production_project_status',
                ],
                default => [],
            };

            if ($permissionKeys === []) {
                return;
            }

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
        Schema::table('production_projects', function (Blueprint $table) {
            $table->dropForeign(['endorsed_by']);
            $table->dropColumn(['tracker_type', 'endorsed_by', 'endorsed_at', 'endorsement_notes']);
        });

        $permissionKeys = [
            'endorse_projects_to_production',
            'view_all_fulfillment_trackers',
            'view_publishing_tracker',
            'view_marketing_tracker',
            'view_events_tracker',
            'view_my_production_tasks',
            'assign_production_projects',
            'update_production_project_status',
        ];
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
