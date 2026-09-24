<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            $table->unsignedInteger('expires_in_days')->nullable()->after('expires_at');
        });

        Schema::create('reward_unlocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reward_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('progress_amount', 12, 2)->default(0);
            $table->timestamp('unlocked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('claim_requested_at')->nullable();
            $table->timestamps();

            $table->unique(['reward_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_unlocks');

        Schema::table('rewards', function (Blueprint $table) {
            $table->dropColumn('expires_in_days');
        });
    }
};
