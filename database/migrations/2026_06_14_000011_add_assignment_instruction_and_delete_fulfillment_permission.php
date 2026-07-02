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
            $table->text('assignment_instruction')->nullable()->after('assigned_to');
            $table->softDeletes();
        });

        DB::table('permissions')->updateOrInsert(
            ['key' => 'delete_fulfillment_records'],
            [
                'group' => 'Production Actions',
                'label' => 'Delete Fulfillment Records',
                'description' => 'Can delete selected fulfillment tracker records.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        $permissionId = DB::table('permissions')->where('key', 'delete_fulfillment_records')->value('id');

        DB::table('roles')
            ->where('name', 'Admin')
            ->get()
            ->each(function ($role) use ($permissionId) {
                if ($permissionId) {
                    DB::table('permission_role')->updateOrInsert([
                        'role_id' => $role->id,
                        'permission_id' => $permissionId,
                    ]);
                }

                $keys = json_decode($role->permissions ?? '[]', true);
                $keys = is_array($keys) ? $keys : [];

                DB::table('roles')
                    ->where('id', $role->id)
                    ->update([
                        'permissions' => json_encode(array_values(array_unique([...$keys, 'delete_fulfillment_records']))),
                        'updated_at' => now(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            $table->dropColumn('assignment_instruction');
            $table->dropSoftDeletes();
        });

        $permissionId = DB::table('permissions')->where('key', 'delete_fulfillment_records')->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }

        DB::table('roles')->get()->each(function ($role) {
            $keys = json_decode($role->permissions ?? '[]', true);

            if (! is_array($keys)) {
                return;
            }

            DB::table('roles')
                ->where('id', $role->id)
                ->update([
                    'permissions' => json_encode(array_values(array_diff($keys, ['delete_fulfillment_records']))),
                    'updated_at' => now(),
                ]);
        });
    }
};
