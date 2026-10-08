<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_account_id')->constrained()->cascadeOnDelete();
            $table->string('folder')->default('INBOX');
            $table->unsignedBigInteger('uid')->nullable();
            $table->string('message_id')->nullable();
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->json('to')->nullable();
            $table->json('cc')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->boolean('is_seen')->default(false);
            $table->boolean('is_answered')->default(false);
            $table->boolean('has_attachments')->default(false);
            $table->timestamps();

            $table->unique(['email_account_id', 'folder', 'uid']);
            $table->index(['email_account_id', 'folder', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_messages');
    }
};
