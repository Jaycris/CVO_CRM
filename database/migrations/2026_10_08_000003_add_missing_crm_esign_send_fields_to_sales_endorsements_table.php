<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_endorsements', 'contract_recipient_email')) {
                $table->string('contract_recipient_email')->nullable()->after('contract_esign_field_values');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_cc_emails')) {
                $table->json('contract_cc_emails')->nullable()->after('contract_recipient_email');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_sent_by')) {
                $table->foreignId('contract_sent_by')->nullable()->after('contract_cc_emails')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (Schema::hasColumn('sales_endorsements', 'contract_sent_by')) {
                $table->dropConstrainedForeignId('contract_sent_by');
            }

            if (Schema::hasColumn('sales_endorsements', 'contract_cc_emails')) {
                $table->dropColumn('contract_cc_emails');
            }

            if (Schema::hasColumn('sales_endorsements', 'contract_recipient_email')) {
                $table->dropColumn('contract_recipient_email');
            }
        });
    }
};
