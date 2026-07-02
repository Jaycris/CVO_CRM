<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTaskItem extends Model
{
    protected $fillable = [
        'production_task_id',
        'service_item_id',
        'name',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(ProductionTask::class, 'production_task_id');
    }

    public function serviceItem(): BelongsTo
    {
        return $this->belongsTo(ServiceInclusion::class, 'service_item_id');
    }
}
