<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_activities') || ! Schema::hasColumn('sales_activities', 'sales_payment_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $this->dropSalesPaymentForeignKeyIfExists();
            DB::statement('ALTER TABLE sales_activities MODIFY sales_payment_id BIGINT UNSIGNED NULL');
            $this->addSalesPaymentForeignKeyIfMissing();

            return;
        }

        Schema::table('sales_activities', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_payment_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Keep this repair irreversible because manual chargebacks store no payment row.
    }

    private function dropSalesPaymentForeignKeyIfExists(): void
    {
        $constraint = DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'sales_activities'
               AND COLUMN_NAME = 'sales_payment_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        if ($constraint?->name) {
            DB::statement(sprintf('ALTER TABLE sales_activities DROP FOREIGN KEY `%s`', str_replace('`', '``', $constraint->name)));
        }
    }

    private function addSalesPaymentForeignKeyIfMissing(): void
    {
        $constraint = DB::selectOne(
            "SELECT CONSTRAINT_NAME AS name
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'sales_activities'
               AND COLUMN_NAME = 'sales_payment_id'
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1"
        );

        if (! $constraint?->name) {
            DB::statement(
                'ALTER TABLE sales_activities
                 ADD CONSTRAINT sales_activities_sales_payment_id_foreign
                 FOREIGN KEY (sales_payment_id) REFERENCES sales_payments(id)
                 ON DELETE CASCADE'
            );
        }
    }
};
