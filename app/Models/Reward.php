<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    public const SCOPE_INDIVIDUAL = 'individual';
    public const SCOPE_TEAM = 'team';
    public const AUDIENCE_ALL = 'all_users';
    public const AUDIENCE_COMMISSION_ELIGIBLE = 'commission_eligible';

    protected $fillable = [
        'created_by',
        'title',
        'accommodation',
        'requirements',
        'requirement_amount',
        'reward_scope',
        'audience',
        'expires_at',
        'expires_in_days',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requirement_amount' => 'decimal:2',
            'expires_at' => 'date',
            'expires_in_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function media(): HasMany
    {
        return $this->hasMany(RewardMedia::class)->orderBy('sort_order');
    }

    public function unlocks(): HasMany
    {
        return $this->hasMany(RewardUnlock::class);
    }
}
