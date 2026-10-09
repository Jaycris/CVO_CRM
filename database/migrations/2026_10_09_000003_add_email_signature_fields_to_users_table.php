<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('email_signature_enabled')->default(true)->after('phone_number');
            $table->string('email_signature_title')->nullable()->after('email_signature_enabled');
            $table->string('email_signature_contact_number')->nullable()->after('email_signature_title');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'email_signature_enabled',
                'email_signature_title',
                'email_signature_contact_number',
            ]);
        });
    }
};
