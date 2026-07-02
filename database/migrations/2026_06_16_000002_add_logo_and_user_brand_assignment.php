<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('address');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('brand_id')
                ->nullable()
                ->after('role_id')
                ->constrained('brands')
                ->nullOnDelete();
        });

        $inkspireBrandId = DB::table('brands')
            ->where('imprint_name', 'Inkspire Media House')
            ->value('id');

        if ($inkspireBrandId) {
            DB::table('users')->update(['brand_id' => $inkspireBrandId]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });

        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('logo_path');
        });
    }
};
