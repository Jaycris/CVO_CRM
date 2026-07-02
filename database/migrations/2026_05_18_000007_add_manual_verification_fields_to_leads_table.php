<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('author_confirmed')->default(false)->after('verify_score');
            $table->boolean('book_confirmed')->default(false)->after('author_confirmed');
            $table->boolean('phone_confirmed')->default(false)->after('book_confirmed');
            $table->boolean('email_confirmed')->default(false)->after('phone_confirmed');
            $table->boolean('linkedin_matched')->default(false)->after('email_confirmed');
            $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['verified_by']);
            $table->dropColumn([
                'author_confirmed',
                'book_confirmed',
                'phone_confirmed',
                'email_confirmed',
                'linkedin_matched',
                'verified_by',
            ]);
        });
    }
};
