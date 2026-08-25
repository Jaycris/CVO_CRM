<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_commission_eligible')) {
                $table->boolean('is_commission_eligible')
                    ->default(false)
                    ->after('is_commission_threshold_exempt');
            }
        });

        DB::table('users')
            ->where('department', 'Sales')
            ->update(['is_commission_eligible' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_commission_eligible')) {
                $table->dropColumn('is_commission_eligible');
            }
        });
    }
};
