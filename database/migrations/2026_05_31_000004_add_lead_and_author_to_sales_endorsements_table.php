<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->foreignId('lead_id')->nullable()->after('agent_id')->constrained('leads')->nullOnDelete();
            $table->string('author_name')->after('frankie_agent_name');
        });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lead_id');
            $table->dropColumn('author_name');
        });
    }
};
