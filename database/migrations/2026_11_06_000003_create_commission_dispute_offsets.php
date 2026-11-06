<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_dispute_offsets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_adjustment_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('commission_release_id')->constrained()->restrictOnDelete();
            $table->date('commission_date');
            $table->date('dispute_date');
            $table->decimal('usd_amount', 14, 2);
            $table->json('commission_snapshot');
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'voided_at']);
            $table->index(['sales_payment_id', 'agent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_dispute_offsets');
    }
};
