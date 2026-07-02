<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained('brands')->nullOnDelete();
            }
        });

        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_endorsements', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained('brands')->nullOnDelete();
            }
        });

        Schema::table('sales_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_payments', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained('brands')->nullOnDelete();
            }
        });

        Schema::table('production_projects', function (Blueprint $table) {
            if (! Schema::hasColumn('production_projects', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained('brands')->nullOnDelete();
            }
        });

        $parentBrandId = DB::table('brands')->where('imprint_name', 'CreatiVision Outsourcing')->value('id')
            ?? DB::table('brands')->value('id');

        DB::table('leads')->whereNull('brand_id')->update([
            'brand_id' => DB::raw('COALESCE(
                (SELECT brand_id FROM users WHERE users.id = leads.created_by),
                (SELECT brand_id FROM users WHERE users.id = leads.assigned_to),
                ' . ((int) $parentBrandId) . '
            )'),
        ]);

        DB::table('sales_endorsements')->whereNull('brand_id')->update([
            'brand_id' => DB::raw('COALESCE(
                (SELECT brand_id FROM leads WHERE leads.id = sales_endorsements.lead_id),
                (SELECT brand_id FROM users WHERE users.id = sales_endorsements.agent_id),
                ' . ((int) $parentBrandId) . '
            )'),
        ]);

        DB::table('sales_payments')->whereNull('brand_id')->update([
            'brand_id' => DB::raw('(SELECT brand_id FROM sales_endorsements WHERE sales_endorsements.id = sales_payments.sales_endorsement_id)'),
        ]);

        DB::table('production_projects')->whereNull('brand_id')->update([
            'brand_id' => DB::raw('(SELECT brand_id FROM sales_endorsements WHERE sales_endorsements.id = production_projects.sales_endorsement_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            if (Schema::hasColumn('production_projects', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });

        Schema::table('sales_payments', function (Blueprint $table) {
            if (Schema::hasColumn('sales_payments', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });

        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (Schema::hasColumn('sales_endorsements', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
        });
    }
};
