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
            $table->string('primary_color', 20)->default('#d97706')->after('logo_path');
            $table->string('accent_color', 20)->default('#fef3c7')->after('primary_color');
            $table->string('site_logo_path')->nullable()->after('accent_color');
        });

        DB::table('brands')
            ->where('imprint_name', 'CreatiVision Outsourcing')
            ->update([
                'primary_color' => '#065f46',
                'accent_color' => '#d1fae5',
            ]);

        DB::table('brands')
            ->where('imprint_name', 'Inkspire Media House')
            ->update([
                'primary_color' => '#d97706',
                'accent_color' => '#fef3c7',
            ]);
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'accent_color', 'site_logo_path']);
        });
    }
};
