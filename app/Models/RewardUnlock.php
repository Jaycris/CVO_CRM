<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardUnlock extends Model
{
    protected $fillable = [
        'reward_id',
        'user_id',
        'period_month',
        'progress_amount',
        'unlocked_at',
        'expires_at',
        'claim_requested_at',
        'claimed_at',
    ];

    protected function casts(): array
    {
        return [
            'progress_amount' => 'decimal:2',
            'period_month' => 'date',
            'unlocked_at' => 'datetime',
            'expires_at' => 'datetime',
            'claim_requested_at' => 'datetime',
            'claimed_at' => 'datetime',
        ];
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
