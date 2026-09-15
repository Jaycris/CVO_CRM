<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (! Schema::hasColumn('brands', 'is_sales_brand')) {
                $table->boolean('is_sales_brand')->default(true)->after('site_logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (Schema::hasColumn('brands', 'is_sales_brand')) {
                $table->dropColumn('is_sales_brand');
            }
        });
    }
};
