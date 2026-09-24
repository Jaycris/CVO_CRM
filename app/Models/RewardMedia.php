<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardMedia extends Model
{
    protected $table = 'reward_media';

    protected $fillable = [
        'reward_id',
        'type',
        'path',
        'original_name',
        'sort_order',
    ];

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }
}
