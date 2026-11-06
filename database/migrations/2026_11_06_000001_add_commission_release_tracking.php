<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_releases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('agent_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');
            $table->string('source')->default('manual');
            $table->string('source_reference')->nullable();
            $table->string('source_key', 64)->nullable()->unique();
            $table->date('released_at')->nullable();
            $table->json('commission_breakdown')->nullable();
            $table->string('reason', 1000);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['sales_payment_id', 'agent_id', 'id']);
        });
        Schema::table('commission_adjustments', function (Blueprint $table) {
            // Null retains the legacy current-month deduction treatment.
            $table->string('settlement_basis')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('commission_adjustments', fn (Blueprint $table) => $table->dropColumn('settlement_basis'));
        Schema::dropIfExists('commission_releases');
    }
};
