<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_activities', function (Blueprint $table) {
            $table->json('commission_snapshot')->nullable();
        });

        Schema::create('commission_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->string('kind');
            $table->date('effective_date');
            $table->decimal('usd_amount', 14, 2);
            $table->json('original_commission');
            $table->timestamps();
            $table->index(['agent_id', 'effective_date']);
            $table->index(['sales_payment_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_adjustments');
        Schema::table('sales_activities', fn (Blueprint $table) => $table->dropColumn('commission_snapshot'));
    }
};
