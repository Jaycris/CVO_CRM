<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->string('contract_status')->nullable()->after('remarks');
            $table->timestamp('contract_sent_at')->nullable()->after('contract_status');
            $table->timestamp('contract_signed_at')->nullable()->after('contract_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->dropColumn([
                'contract_status',
                'contract_sent_at',
                'contract_signed_at',
            ]);
        });
    }
};
