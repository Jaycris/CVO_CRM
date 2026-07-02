<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teams')) {
            Schema::create('teams', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->string('department');
                $table->string('name');
                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('team_leader_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->unique(['brand_id', 'department', 'name']);
            });
        }

        if (! Schema::hasColumn('users', 'team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('team_id')->nullable()->after('brand_id')->constrained('teams')->nullOnDelete();
            });
        }

        DB::table('permissions')->updateOrInsert(
            ['key' => 'manage_teams'],
            [
                'group' => 'Administration',
                'label' => 'Manage Teams',
                'description' => 'Can create and edit teams for each brand, department, manager, and team leader.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'manage_teams')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('permission_role')->updateOrInsert([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'team_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('team_id');
            });
        }

        Schema::dropIfExists('teams');

        $permissionId = DB::table('permissions')->where('key', 'manage_teams')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
