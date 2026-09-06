<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_endorsements', 'amount_to_be_paid')) {
                $table->decimal('amount_to_be_paid', 12, 2)->nullable()->after('amount');
            }
        });

        Schema::table('sales_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_payments', 'amount')) {
                $table->decimal('amount', 12, 2)->nullable()->after('sales_endorsement_id');
            }
        });

        DB::table('sales_endorsements')
            ->whereNull('amount_to_be_paid')
            ->update(['amount_to_be_paid' => DB::raw('amount')]);

        DB::table('sales_payments')
            ->whereNull('amount')
            ->update([
                'amount' => DB::raw('COALESCE((SELECT amount_to_be_paid FROM sales_endorsements WHERE sales_endorsements.id = sales_payments.sales_endorsement_id), (SELECT amount FROM sales_endorsements WHERE sales_endorsements.id = sales_payments.sales_endorsement_id), 0)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('sales_payments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_payments', 'amount')) {
                $table->dropColumn('amount');
            }
        });

        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (Schema::hasColumn('sales_endorsements', 'amount_to_be_paid')) {
                $table->dropColumn('amount_to_be_paid');
            }
        });
    }
};
