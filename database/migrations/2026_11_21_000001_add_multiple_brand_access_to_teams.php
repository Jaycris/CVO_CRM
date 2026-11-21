<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', fn (Blueprint $table) => $table->boolean('multiple_brands_enabled')->default(false));
        Schema::create('brand_team', function (Blueprint $table) {
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->primary(['team_id', 'brand_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_team');
        Schema::table('teams', fn (Blueprint $table) => $table->dropColumn('multiple_brands_enabled'));
    }
};
