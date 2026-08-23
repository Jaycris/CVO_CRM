<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'hris_employee_id')) {
                $table->string('hris_employee_id', 50)
                    ->nullable()
                    ->after('phone_number')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'hris_employee_id')) {
                $table->dropIndex(['hris_employee_id']);
                $table->dropColumn('hris_employee_id');
            }
        });
    }
};
