<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_endorsements', 'contract_file_path')) {
                $table->string('contract_file_path')->nullable()->after('contract_signed_at');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_file_name')) {
                $table->string('contract_file_name')->nullable()->after('contract_file_path');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_file_uploaded_at')) {
                $table->timestamp('contract_file_uploaded_at')->nullable()->after('contract_file_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->dropColumn([
                'contract_file_path',
                'contract_file_name',
                'contract_file_uploaded_at',
            ]);
        });
    }
};
