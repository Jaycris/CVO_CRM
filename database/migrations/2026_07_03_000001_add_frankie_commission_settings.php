<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        DB::table('app_settings')->updateOrInsert(
            ['key' => 'frankie_commission_percent'],
            [
                'value' => '50',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->foreignId('frankie_agent_id')
                ->nullable()
                ->after('frankie_agent_name')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('frankie_commission_percent', 5, 2)
                ->nullable()
                ->after('frankie_agent_id');
        });

        Schema::table('sales_activities', function (Blueprint $table) {
            $table->foreignId('frankie_agent_id')
                ->nullable()
                ->after('agent_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->decimal('agent_credit_amount', 12, 2)
                ->default(0)
                ->after('amount');
            $table->decimal('frankie_credit_amount', 12, 2)
                ->default(0)
                ->after('agent_credit_amount');
            $table->decimal('frankie_commission_percent', 5, 2)
                ->nullable()
                ->after('frankie_credit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales_activities', function (Blueprint $table) {
            $table->dropForeign(['frankie_agent_id']);
            $table->dropColumn([
                'frankie_agent_id',
                'agent_credit_amount',
                'frankie_credit_amount',
                'frankie_commission_percent',
            ]);
        });

        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->dropForeign(['frankie_agent_id']);
            $table->dropColumn(['frankie_agent_id', 'frankie_commission_percent']);
        });

        Schema::dropIfExists('app_settings');
    }
};
