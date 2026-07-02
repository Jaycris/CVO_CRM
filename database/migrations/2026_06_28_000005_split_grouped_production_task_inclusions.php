<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('production_tasks')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->each(function ($task) {
                $items = DB::table('production_task_items')
                    ->where('production_task_id', $task->id)
                    ->orderBy('id')
                    ->get();

                if ($items->count() <= 1) {
                    if ($items->count() === 1 && $task->title !== $items->first()->name) {
                        DB::table('production_tasks')
                            ->where('id', $task->id)
                            ->update([
                                'title' => $items->first()->name,
                                'updated_at' => now(),
                            ]);
                    }

                    return;
                }

                $firstItem = $items->first();

                DB::table('production_tasks')
                    ->where('id', $task->id)
                    ->update([
                        'title' => $firstItem->name,
                        'updated_at' => now(),
                    ]);

                DB::table('production_task_items')
                    ->where('production_task_id', $task->id)
                    ->where('id', '!=', $firstItem->id)
                    ->delete();

                $items->skip(1)->each(function ($item) use ($task) {
                    $newTaskId = DB::table('production_tasks')->insertGetId([
                        'production_project_id' => $task->production_project_id,
                        'assigned_to' => $task->assigned_to,
                        'title' => $item->name,
                        'instructions' => $task->instructions,
                        'due_date' => $task->due_date,
                        'status' => $task->status,
                        'progress' => $task->progress,
                        'notes' => $task->notes,
                        'result_link' => $task->result_link ?? null,
                        'started_at' => $task->started_at,
                        'completed_at' => $task->completed_at,
                        'created_at' => $task->created_at ?? now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('production_task_items')->insert([
                        'production_task_id' => $newTaskId,
                        'service_item_id' => $item->service_item_id,
                        'name' => $item->name,
                        'created_at' => $item->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                });
            });
    }

    public function down(): void
    {
        // Data normalization is intentionally not reversed.
    }
};
