<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('announcement');
            $table->string('title');
            $table->text('message');
            $table->string('button_text')->nullable();
            $table->text('button_url')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['brand_id', 'is_active']);
            $table->index(['starts_at', 'ends_at']);
        });

        DB::table('permissions')->updateOrInsert(
            ['key' => 'manage_dashboard_banners'],
            [
                'group' => 'Administration',
                'label' => 'Manage Dashboard Banners',
                'description' => 'Can create, edit, and remove hero banners shown on the dashboard.',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'manage_dashboard_banners')->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('roles')
            ->where('name', 'Admin')
            ->pluck('id')
            ->each(function ($roleId) use ($permissionId) {
                DB::table('permission_role')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            });
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'manage_dashboard_banners')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permission_user')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        Schema::dropIfExists('dashboard_banners');
    }
};
