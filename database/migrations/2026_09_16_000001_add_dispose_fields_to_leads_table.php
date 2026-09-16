<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'disposed_at')) {
                $table->timestamp('disposed_at')->nullable()->after('archived_by');
            }

            if (! Schema::hasColumn('leads', 'disposed_by')) {
                $table->foreignId('disposed_by')->nullable()->after('disposed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('leads', 'dispose_reason')) {
                $table->string('dispose_reason')->nullable()->after('disposed_by');
            }

            if (! Schema::hasColumn('leads', 'dispose_notes')) {
                $table->text('dispose_notes')->nullable()->after('dispose_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'disposed_by')) {
                $table->dropConstrainedForeignId('disposed_by');
            }

            foreach (['disposed_at', 'dispose_reason', 'dispose_notes'] as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
