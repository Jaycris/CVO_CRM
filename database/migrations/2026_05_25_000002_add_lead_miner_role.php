<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('departments')->updateOrInsert(
            ['name' => 'Lead Generation'],
            [
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        DB::table('roles')->updateOrInsert(
            ['slug' => 'lead-miner'],
            [
                'name' => 'Lead Miner',
                'department' => 'Lead Generation',
                'description' => 'Adds, edits, reassigns, receives returned, and archives own leads.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'lead-miner')->delete();
    }
};
