<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'commission_threshold_amount')) {
                $table->decimal('commission_threshold_amount', 10, 2)
                    ->default(500)
                    ->after('markup_commission_percent');
            }

            if (! Schema::hasColumn('users', 'is_commission_threshold_exempt')) {
                $table->boolean('is_commission_threshold_exempt')
                    ->default(false)
                    ->after('commission_threshold_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_commission_threshold_exempt')) {
                $table->dropColumn('is_commission_threshold_exempt');
            }

            if (Schema::hasColumn('users', 'commission_threshold_amount')) {
                $table->dropColumn('commission_threshold_amount');
            }
        });
    }
};
