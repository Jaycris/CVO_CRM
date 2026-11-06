<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_adjustment_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_adjustment_id')->unique('car_adjustment_unique')->constrained(indexName: 'car_adjustment_fk')->restrictOnDelete();
            $table->foreignId('commission_release_id')->constrained(indexName: 'car_release_fk')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users', indexName: 'car_creator_fk')->restrictOnDelete();
            $table->json('before_state');
            $table->json('after_state');
            $table->string('reason', 1000);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_adjustment_reconciliations');
    }
};
