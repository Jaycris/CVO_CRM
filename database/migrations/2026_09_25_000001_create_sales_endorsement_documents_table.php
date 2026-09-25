<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_endorsement_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_endorsement_id')->constrained('sales_endorsements')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 30)->index();
            $table->string('file_path');
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->timestamps();
        });

        DB::table('sales_endorsements')
            ->whereNotNull('contract_file_path')
            ->orderBy('id')
            ->get([
                'id',
                'contract_file_path',
                'contract_file_name',
                'contract_file_uploaded_at',
                'created_at',
                'updated_at',
            ])
            ->each(function ($endorsement) {
                DB::table('sales_endorsement_documents')->insert([
                    'sales_endorsement_id' => $endorsement->id,
                    'uploaded_by' => null,
                    'document_type' => 'contract',
                    'file_path' => $endorsement->contract_file_path,
                    'file_name' => $endorsement->contract_file_name,
                    'file_size' => null,
                    'mime_type' => null,
                    'created_at' => $endorsement->contract_file_uploaded_at ?? $endorsement->created_at ?? now(),
                    'updated_at' => $endorsement->contract_file_uploaded_at ?? $endorsement->updated_at ?? now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_endorsement_documents');
    }
};
