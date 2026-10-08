<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_endorsements', 'contract_signer_name')) {
                $table->string('contract_signer_name')->nullable()->after('contract_signed_at');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_signer_email')) {
                $table->string('contract_signer_email')->nullable()->after('contract_signer_name');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_signature_text')) {
                $table->string('contract_signature_text')->nullable()->after('contract_signer_email');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_signer_ip')) {
                $table->string('contract_signer_ip', 45)->nullable()->after('contract_signature_text');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_signer_user_agent')) {
                $table->text('contract_signer_user_agent')->nullable()->after('contract_signer_ip');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_esign_fields')) {
                $table->json('contract_esign_fields')->nullable()->after('contract_signer_user_agent');
            }

            if (! Schema::hasColumn('sales_endorsements', 'contract_esign_field_values')) {
                $table->json('contract_esign_field_values')->nullable()->after('contract_esign_fields');
            }

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
            $columns = [
                'contract_signer_name',
                'contract_signer_email',
                'contract_signature_text',
                'contract_signer_ip',
                'contract_signer_user_agent',
                'contract_esign_fields',
                'contract_esign_field_values',
                'contract_recipient_email',
                'contract_cc_emails',
                'contract_sent_by',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sales_endorsements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
