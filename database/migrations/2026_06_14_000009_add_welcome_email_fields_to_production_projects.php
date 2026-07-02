<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            $table->string('welcome_email_status')->default('pending')->after('endorsement_notes');
            $table->text('welcome_email_reason')->nullable()->after('welcome_email_status');
        });
    }

    public function down(): void
    {
        Schema::table('production_projects', function (Blueprint $table) {
            $table->dropColumn(['welcome_email_status', 'welcome_email_reason']);
        });
    }
};
