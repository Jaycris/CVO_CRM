<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('commission_profile_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_profile_id')->constrained()->cascadeOnDelete();
            $table->decimal('minimum_mtd_percent', 5, 2)->default(0);
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'commission_profile_id')) {
                $table->foreignId('commission_profile_id')
                    ->nullable()
                    ->after('service_commission_percent')
                    ->constrained('commission_profiles')
                    ->nullOnDelete();
            }
        });

        $defaultProfileId = DB::table('commission_profiles')->insertGetId([
            'name' => 'Default Service Tiers',
            'description' => '15% below 75%, 20% at 75%, and 25% at 100% Service MTD.',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('commission_profile_rules')->insert([
            [
                'commission_profile_id' => $defaultProfileId,
                'minimum_mtd_percent' => 0,
                'commission_percent' => 15,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'commission_profile_id' => $defaultProfileId,
                'minimum_mtd_percent' => 75,
                'commission_percent' => 20,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'commission_profile_id' => $defaultProfileId,
                'minimum_mtd_percent' => 100,
                'commission_percent' => 25,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('users')
            ->whereNull('commission_profile_id')
            ->update(['commission_profile_id' => $defaultProfileId]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'commission_profile_id')) {
                $table->dropConstrainedForeignId('commission_profile_id');
            }
        });

        Schema::dropIfExists('commission_profile_rules');
        Schema::dropIfExists('commission_profiles');
    }
};
