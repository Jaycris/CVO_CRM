<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('end_of_shift_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('report_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('report_to_hris_employee_id', 50)->nullable()->index();
            $table->date('shift_date');
            $table->text('work_done');
            $table->text('pending_work')->nullable();
            $table->text('blockers')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['user_id', 'shift_date']);
            $table->index(['report_to_user_id', 'shift_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('end_of_shift_reports');
    }
};
