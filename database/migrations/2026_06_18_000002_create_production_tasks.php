<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->foreignId('service_id')
                ->nullable()
                ->after('services')
                ->constrained('services')
                ->nullOnDelete();
        });

        Schema::create('production_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_project_id')->constrained('production_projects')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->unsignedTinyInteger('progress')->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assigned_to', 'status']);
            $table->index(['production_project_id', 'status']);
        });

        Schema::create('production_task_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_task_id')->constrained('production_tasks')->cascadeOnDelete();
            $table->foreignId('service_item_id')->nullable()->constrained('service_items')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['production_task_id', 'service_item_id']);
        });

        DB::table('sales_endorsements')
            ->whereNull('service_id')
            ->orderBy('id')
            ->get()
            ->each(function ($endorsement) {
                $serviceId = DB::table('services')
                    ->where('brand_id', $endorsement->brand_id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $endorsement->services))])
                    ->value('id');

                if ($serviceId) {
                    DB::table('sales_endorsements')
                        ->where('id', $endorsement->id)
                        ->update(['service_id' => $serviceId]);
                }
            });

        DB::table('production_projects')
            ->whereNotNull('assigned_to')
            ->orderBy('id')
            ->get()
            ->each(function ($project) {
                $taskId = DB::table('production_tasks')->insertGetId([
                    'production_project_id' => $project->id,
                    'assigned_to' => $project->assigned_to,
                    'title' => 'Production Work',
                    'instructions' => $project->assignment_instruction,
                    'status' => $project->status,
                    'progress' => match ($project->status) {
                        'fulfilled' => 100,
                        'in_progress' => 50,
                        default => 0,
                    },
                    'notes' => $project->notes,
                    'started_at' => $project->started_at,
                    'completed_at' => $project->completed_at,
                    'created_at' => $project->created_at ?? now(),
                    'updated_at' => $project->updated_at ?? now(),
                ]);

                $serviceItems = DB::table('production_projects')
                    ->join('sales_endorsements', 'sales_endorsements.id', '=', 'production_projects.sales_endorsement_id')
                    ->join('service_items', 'service_items.service_id', '=', 'sales_endorsements.service_id')
                    ->where('production_projects.id', $project->id)
                    ->select('service_items.id', 'service_items.name')
                    ->get();

                foreach ($serviceItems as $item) {
                    DB::table('production_task_items')->insert([
                        'production_task_id' => $taskId,
                        'service_item_id' => $item->id,
                        'name' => $item->name,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_task_items');
        Schema::dropIfExists('production_tasks');

        Schema::table('sales_endorsements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }
};
