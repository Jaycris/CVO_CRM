<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_activities')) {
            return;
        }

        Schema::table('sales_activities', function (Blueprint $table) {
            $table->foreignId('sales_payment_id')->nullable()->change();

            if (! Schema::hasColumn('sales_activities', 'original_sold_date')) {
                $table->date('original_sold_date')->nullable()->after('sold_date');
            }

            if (! Schema::hasColumn('sales_activities', 'chargeback_reason')) {
                $table->text('chargeback_reason')->nullable()->after('original_sold_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sales_activities')) {
            return;
        }

        Schema::table('sales_activities', function (Blueprint $table) {
            if (Schema::hasColumn('sales_activities', 'chargeback_reason')) {
                $table->dropColumn('chargeback_reason');
            }

            if (Schema::hasColumn('sales_activities', 'original_sold_date')) {
                $table->dropColumn('original_sold_date');
            }
        });
    }
};
