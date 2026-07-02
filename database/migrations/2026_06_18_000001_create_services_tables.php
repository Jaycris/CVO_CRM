<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            Schema::create('services', function (Blueprint $table) {
                $table->id();
                $table->foreignId('brand_id')->constrained('brands')->cascadeOnDelete();
                $table->string('name');
                $table->string('category');
                $table->decimal('price', 12, 2)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['brand_id', 'name']);
            });
        }

        if (! Schema::hasTable('service_items')) {
            Schema::create('service_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->string('name');
                $table->string('assigned_position')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        DB::table('permissions')->insertOrIgnore([
            'key' => 'manage_services',
            'group' => 'Administration',
            'label' => 'Manage Services',
            'description' => 'Can create, edit, and delete brand/account services and inclusions.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->where('key', 'manage_services')->value('id');
        $adminRoleId = DB::table('roles')->where('name', 'Admin')->value('id');

        if ($permissionId && $adminRoleId) {
            DB::table('permission_role')->insertOrIgnore([
                'permission_id' => $permissionId,
                'role_id' => $adminRoleId,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
        Schema::dropIfExists('services');

        $permissionId = DB::table('permissions')->where('key', 'manage_services')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
