<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'enable_end_of_shift_report')) {
                $table->boolean('enable_end_of_shift_report')
                    ->default(false)
                    ->after('is_commission_eligible');
            }

            if (! Schema::hasColumn('users', 'reports_to_hris_employee_id')) {
                $table->string('reports_to_hris_employee_id', 50)
                    ->nullable()
                    ->after('hris_employee_id')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'reports_to_hris_employee_id')) {
                $table->dropIndex(['reports_to_hris_employee_id']);
                $table->dropColumn('reports_to_hris_employee_id');
            }

            if (Schema::hasColumn('users', 'enable_end_of_shift_report')) {
                $table->dropColumn('enable_end_of_shift_report');
            }
        });
    }
};
