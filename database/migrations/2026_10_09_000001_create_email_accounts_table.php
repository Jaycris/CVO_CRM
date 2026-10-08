<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->string('email_address');
            $table->string('username');
            $table->text('encrypted_password')->nullable();
            $table->string('imap_host')->default('mail.siteground.net');
            $table->unsignedSmallInteger('imap_port')->default(993);
            $table->string('imap_encryption', 20)->default('ssl');
            $table->string('smtp_host')->default('mail.siteground.net');
            $table->unsignedSmallInteger('smtp_port')->default(465);
            $table->string('smtp_encryption', 20)->default('ssl');
            $table->boolean('is_shared')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'email_address']);
            $table->index(['brand_id', 'is_shared']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_accounts');
    }
};
