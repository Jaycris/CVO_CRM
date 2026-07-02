<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionProject extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'sales_endorsement_id',
        'tracker_type',
        'fulfillment_officer_id',
        'assigned_to',
        'assignment_instruction',
        'endorsed_by',
        'endorsed_at',
        'endorsement_notes',
        'welcome_email_status',
        'welcome_email_reason',
        'status',
        'started_at',
        'completed_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'endorsed_at' => 'datetime',
        ];
    }

    public function endorsement(): BelongsTo
    {
        return $this->belongsTo(SalesEndorsement::class, 'sales_endorsement_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function fulfillmentOfficer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fulfillment_officer_id');
    }

    public function endorsedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'endorsed_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(ProductionTask::class);
    }

    public function getProgressPercentageAttribute(): int
    {
        $tasks = $this->relationLoaded('tasks')
            ? $this->tasks
            : $this->tasks()->withCount('items')->get();

        if ($tasks->isEmpty()) {
            return 0;
        }

        $totalWeight = $tasks->sum(fn ($task) => max(1, (int) ($task->items_count ?? $task->items->count())));
        $earned = $tasks->sum(function ($task) {
            $weight = max(1, (int) ($task->items_count ?? $task->items->count()));

            return $weight * max(0, min(100, (int) $task->progress));
        });

        return (int) round($earned / max(1, $totalWeight));
    }

    public function syncStatusFromTasks(): void
    {
        $tasks = $this->tasks()->get();

        if ($tasks->isEmpty()) {
            return;
        }

        $status = match (true) {
            $tasks->every(fn ($task) => $task->status === 'fulfilled') => 'fulfilled',
            $tasks->every(fn ($task) => $task->status === 'hold_off') => 'hold_off',
            $tasks->contains(fn ($task) => in_array($task->status, ['in_progress', 'fulfilled'], true)) => 'in_progress',
            default => 'pending',
        };

        $this->update([
            'status' => $status,
            'started_at' => $status !== 'pending' ? ($this->started_at ?? now()) : $this->started_at,
            'completed_at' => $status === 'fulfilled' ? ($this->completed_at ?? now()) : null,
        ]);
    }
}
