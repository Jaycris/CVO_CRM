<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('invitation_expires_at');
            $table->foreignId('suspended_by')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable()->after('suspended_by');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('previous_agent_id')->nullable()->after('assigned_date')->constrained('users')->nullOnDelete();
            $table->timestamp('previous_agent_released_at')->nullable()->after('previous_agent_id');
            $table->string('previous_agent_release_reason')->nullable()->after('previous_agent_released_at');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['previous_agent_id']);
            $table->dropColumn([
                'previous_agent_id',
                'previous_agent_released_at',
                'previous_agent_release_reason',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['suspended_by']);
            $table->dropColumn([
                'suspended_at',
                'suspended_by',
                'suspension_reason',
            ]);
        });
    }
};
