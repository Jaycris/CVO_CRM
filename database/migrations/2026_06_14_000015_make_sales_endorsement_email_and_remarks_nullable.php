<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
            $table->text('remarks')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
            $table->text('remarks')->nullable(false)->change();
        });
    }
};
