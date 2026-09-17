<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'lead_tag')) {
                $table->string('lead_tag')->nullable()->after('published_date');
            }

            if (! Schema::hasColumn('leads', 'sales_notes')) {
                $table->text('sales_notes')->nullable()->after('lead_tag');
            }
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'sales_notes')) {
                $table->dropColumn('sales_notes');
            }

            if (Schema::hasColumn('leads', 'lead_tag')) {
                $table->dropColumn('lead_tag');
            }
        });
    }
};
