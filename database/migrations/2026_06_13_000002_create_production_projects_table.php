<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_endorsement_id')->unique()->constrained('sales_endorsements')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $permissions = [
            'view_production_projects' => [
                'Production Pages',
                'View Production Projects',
                'Can see production projects endorsed after successful payment and signed contract.',
            ],
            'manage_production_projects' => [
                'Production Actions',
                'Manage Production Projects',
                'Can assign fulfillment owners and update production project status.',
            ],
            'view_client_project_progress' => [
                'Sales Pages',
                'View Client Project Progress',
                'Can see Fulfillment Tracker progress for the authenticated sales agent clients.',
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

        $rolePermissions = [
            'Admin' => array_keys($permissions),
            'Fulfillment Officer' => ['view_production_projects', 'manage_production_projects'],
            'Operation Manager' => ['view_production_projects', 'manage_production_projects'],
            'Branding Specialist' => ['view_client_project_progress'],
            'Sales Director' => ['view_client_project_progress'],
            'Team Leader' => ['view_client_project_progress'],
        ];

        DB::table('roles')->get()->each(function ($role) use ($rolePermissions, $permissionIds) {
            $permissionKeys = $rolePermissions[$role->name] ?? [];

            if ($role->name === 'Team Leader' && $role->department !== 'Sales') {
                $permissionKeys = [];
            }

            if ($role->name === 'Operation Manager' && $role->department !== 'Production') {
                $permissionKeys = [];
            }

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

        DB::table('sales_endorsements')
            ->join('sales_payments', 'sales_endorsements.id', '=', 'sales_payments.sales_endorsement_id')
            ->where('sales_payments.status', 'Payment Success')
            ->where('sales_endorsements.contract_status', 'signed')
            ->whereNull('sales_endorsements.deleted_at')
            ->select('sales_endorsements.id')
            ->orderBy('sales_endorsements.id')
            ->get()
            ->each(function ($endorsement) {
                DB::table('production_projects')->updateOrInsert(
                    ['sales_endorsement_id' => $endorsement->id],
                    [
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_projects');

        $permissionKeys = ['view_production_projects', 'manage_production_projects', 'view_client_project_progress'];
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
