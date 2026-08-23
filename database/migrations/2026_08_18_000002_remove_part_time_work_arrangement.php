<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('work_type', 'part_time')
            ->update(['work_type' => null]);
    }

    public function down(): void
    {
        //
    }
};
