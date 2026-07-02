<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('production_tasks', 'result_link')) {
                $table->text('result_link')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('production_tasks', 'result_link')) {
                $table->dropColumn('result_link');
            }
        });
    }
};
