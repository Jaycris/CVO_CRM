<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reward_unlocks', 'period_month')) {
            Schema::table('reward_unlocks', function (Blueprint $table) {
                $table->date('period_month')->nullable()->after('user_id');
            });
        }

        DB::table('reward_unlocks')
            ->whereNull('period_month')
            ->update([
                'period_month' => DB::raw("DATE_FORMAT(COALESCE(unlocked_at, created_at, NOW()), '%Y-%m-01')"),
            ]);

        DB::statement('ALTER TABLE reward_unlocks MODIFY period_month DATE NOT NULL');

        if (! $this->indexExists('reward_unlocks', 'reward_unlocks_reward_id_user_id_period_month_unique')) {
            Schema::table('reward_unlocks', function (Blueprint $table) {
                $table->unique(['reward_id', 'user_id', 'period_month']);
            });
        }

        Schema::table('reward_unlocks', function (Blueprint $table) {
            if ($this->indexExists('reward_unlocks', 'reward_unlocks_reward_id_user_id_unique')) {
                $table->dropUnique(['reward_id', 'user_id']);
            }

            if (! $this->indexExists('reward_unlocks', 'reward_unlocks_user_id_period_month_index')) {
                $table->index(['user_id', 'period_month']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reward_unlocks', function (Blueprint $table) {
            $table->dropUnique(['reward_id', 'user_id', 'period_month']);
            $table->dropIndex(['user_id', 'period_month']);
            $table->unique(['reward_id', 'user_id']);
            $table->dropColumn('period_month');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$index]))->isNotEmpty();
    }
};
