<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sales_payments') || ! Schema::hasColumn('sales_payments', 'sales_endorsement_id')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            $uniqueIndex = DB::selectOne(
                "SELECT INDEX_NAME AS name
                 FROM INFORMATION_SCHEMA.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'sales_payments'
                   AND COLUMN_NAME = 'sales_endorsement_id'
                   AND NON_UNIQUE = 0
                 LIMIT 1"
            );

            if ($uniqueIndex?->name) {
                $plainIndex = DB::selectOne(
                    "SELECT INDEX_NAME AS name
                     FROM INFORMATION_SCHEMA.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = 'sales_payments'
                       AND COLUMN_NAME = 'sales_endorsement_id'
                       AND NON_UNIQUE = 1
                     LIMIT 1"
                );

                if (! $plainIndex?->name) {
                    DB::statement('ALTER TABLE sales_payments ADD INDEX sales_payments_sales_endorsement_id_index (sales_endorsement_id)');
                }

                DB::statement(sprintf('ALTER TABLE sales_payments DROP INDEX `%s`', str_replace('`', '``', $uniqueIndex->name)));
            }

            return;
        }

        try {
            Schema::table('sales_payments', function ($table) {
                $table->dropUnique(['sales_endorsement_id']);
            });
        } catch (Throwable) {
            // Some local SQLite databases cannot drop indexes created inline; fresh installs use the updated base migration.
        }
    }

    public function down(): void
    {
        // Do not restore the unique index because recurring/installment endorsements can have multiple payment records.
    }
};
