<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $adminRoleIds = DB::table('roles')
            ->where('name', 'Admin')
            ->pluck('id');

        DB::table('users')
            ->whereIn('role_id', $adminRoleIds)
            ->update(['brand_id' => null]);
    }

    public function down(): void
    {
        $inkspireBrandId = DB::table('brands')
            ->where('imprint_name', 'Inkspire Media House')
            ->value('id');

        if (! $inkspireBrandId) {
            return;
        }

        $adminRoleIds = DB::table('roles')
            ->where('name', 'Admin')
            ->pluck('id');

        DB::table('users')
            ->whereIn('role_id', $adminRoleIds)
            ->whereNull('brand_id')
            ->update(['brand_id' => $inkspireBrandId]);
    }
};
