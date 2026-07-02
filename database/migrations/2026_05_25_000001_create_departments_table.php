<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        $departments = collect(['Sales', 'Administration', 'Production'])
            ->merge(DB::table('roles')->whereNotNull('department')->pluck('department'))
            ->merge(DB::table('users')->whereNotNull('department')->pluck('department'))
            ->filter()
            ->unique()
            ->values();

        foreach ($departments as $department) {
            DB::table('departments')->insert([
                'name' => $department,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
