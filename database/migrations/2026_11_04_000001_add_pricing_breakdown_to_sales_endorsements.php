<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            // Null identifies legacy endorsements; their original pricing remains unchanged.
            $table->decimal('service_price_snapshot', 12, 2)->nullable();
            $table->decimal('markup_amount', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', fn (Blueprint $table) => $table->dropColumn(['service_price_snapshot', 'markup_amount']));
    }
};
