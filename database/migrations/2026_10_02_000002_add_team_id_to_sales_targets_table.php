<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_targets') && ! Schema::hasColumn('sales_targets', 'team_id')) {
            Schema::table('sales_targets', function (Blueprint $table) {
                $table->foreignId('team_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('teams')
                    ->nullOnDelete();

                $table->index(['team_id', 'target_month']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales_targets') && Schema::hasColumn('sales_targets', 'team_id')) {
            Schema::table('sales_targets', function (Blueprint $table) {
                $table->dropIndex(['team_id', 'target_month']);
                $table->dropConstrainedForeignId('team_id');
            });
        }
    }
};
