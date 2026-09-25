<?php

use App\Models\Reward;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rewards', function (Blueprint $table) {
            if (! Schema::hasColumn('rewards', 'requirement_type')) {
                $table->string('requirement_type')
                    ->default(Reward::REQUIREMENT_SALES_MTD)
                    ->after('requirements');
            }
        });

        DB::table('rewards')
            ->where('reward_scope', Reward::SCOPE_TEAM)
            ->update(['reward_scope' => Reward::SCOPE_COMPANY]);
    }

    public function down(): void
    {
        DB::table('rewards')
            ->where('reward_scope', Reward::SCOPE_COMPANY)
            ->update(['reward_scope' => Reward::SCOPE_TEAM]);

        Schema::table('rewards', function (Blueprint $table) {
            if (Schema::hasColumn('rewards', 'requirement_type')) {
                $table->dropColumn('requirement_type');
            }
        });
    }
};
