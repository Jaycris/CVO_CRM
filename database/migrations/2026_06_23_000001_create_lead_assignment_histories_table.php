<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_assignment_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->foreignId('agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('release_reason')->nullable();
            $table->timestamps();

            $table->index(['lead_id', 'released_at']);
            $table->index(['agent_id', 'released_at']);
        });

        DB::table('leads')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->chunkById(200, function ($leads) {
                $now = now();

                DB::table('lead_assignment_histories')->insert(
                    $leads->map(fn ($lead) => [
                        'lead_id' => $lead->id,
                        'agent_id' => $lead->assigned_to,
                        'assigned_by' => null,
                        'assigned_at' => $lead->assigned_date
                            ? $lead->assigned_date.' 00:00:00'
                            : ($lead->created_at ?? $now),
                        'released_at' => null,
                        'released_by' => null,
                        'release_reason' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_assignment_histories');
    }
};
