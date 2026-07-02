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
            $table->string('crm_display_name')->nullable()->after('imprint_name');
        });

        DB::table('brands')
            ->where('imprint_name', 'CreatiVision Outsourcing')
            ->update(['crm_display_name' => 'CreatiVision CRM']);

        DB::table('brands')
            ->where('imprint_name', 'Inkspire Media House')
            ->update(['crm_display_name' => 'Inkspire Media House CRM']);
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $table->dropColumn('crm_display_name');
        });
    }
};
