<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'service_commission_percent')) {
                $table->decimal('service_commission_percent', 5, 2)
                    ->default(20)
                    ->after('phone_number');
            }

            if (! Schema::hasColumn('users', 'markup_commission_percent')) {
                $table->decimal('markup_commission_percent', 5, 2)
                    ->default(50)
                    ->after('service_commission_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'markup_commission_percent')) {
                $table->dropColumn('markup_commission_percent');
            }

            if (Schema::hasColumn('users', 'service_commission_percent')) {
                $table->dropColumn('service_commission_percent');
            }
        });
    }
};
