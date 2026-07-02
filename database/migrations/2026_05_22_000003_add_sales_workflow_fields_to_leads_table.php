<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('sales_stage')->nullable()->after('return_notes');
            $table->timestamp('sales_stage_updated_at')->nullable()->after('sales_stage');
            $table->timestamp('archived_at')->nullable()->after('sales_stage_updated_at');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['archived_by']);
            $table->dropColumn([
                'sales_stage',
                'sales_stage_updated_at',
                'archived_at',
                'archived_by',
            ]);
        });
    }
};
