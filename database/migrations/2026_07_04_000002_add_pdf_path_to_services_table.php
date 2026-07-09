<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'pdf_path')) {
            Schema::table('services', function (Blueprint $table) {
                $table->string('pdf_path')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('services') && Schema::hasColumn('services', 'pdf_path')) {
            Schema::table('services', function (Blueprint $table) {
                $table->dropColumn('pdf_path');
            });
        }
    }
};
