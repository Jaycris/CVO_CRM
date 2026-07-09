<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->date('target_month');
            $table->string('target_type');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('work_setup')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
            $table->index(['brand_id', 'target_month', 'target_type']);
            $table->index(['user_id', 'target_month']);
        });

        $permissions = [
            'view_sales_performance_mtd' => [
                'Reports',
                'View Sales Performance MTD',
                'Can view monthly sales target performance, Global MTD, Remote MTD, Site MTD, and agent MTD rows.',
            ],
            'manage_sales_targets' => [
                'Reports',
                'Manage Sales Targets',
                'Can update Global, Remote, Site, and agent sales targets for each month.',
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

        $permissionIds = DB::table('permissions')->whereIn('key', array_keys($permissions))->pluck('id');

        DB::table('roles')
            ->whereIn('name', ['Admin', 'Sales Director', 'Operation Manager'])
            ->pluck('id')
            ->each(function ($roleId) use ($permissionIds) {
                foreach ($permissionIds as $permissionId) {
                    DB::table('permission_role')->insertOrIgnore([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            });
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['view_sales_performance_mtd', 'manage_sales_targets'])
            ->pluck('id');

        DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permission_user')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('sales_targets');
    }
};
