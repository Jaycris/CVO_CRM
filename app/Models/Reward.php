<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    public const SCOPE_INDIVIDUAL = 'individual';
    public const SCOPE_TEAM = 'team';
    public const SCOPE_COMPANY = 'company';
    public const AUDIENCE_ALL = 'all_users';
    public const AUDIENCE_COMMISSION_ELIGIBLE = 'commission_eligible';
    public const AUDIENCE_LEAD_GENERATION = 'lead_generation';
    public const AUDIENCE_PRODUCTION = 'production';
    public const REQUIREMENT_SALES_MTD = 'sales_mtd';
    public const REQUIREMENT_SOLD_MINED_LEADS = 'sold_mined_leads';
    public const REQUIREMENT_SOLD_VERIFIED_LEADS = 'sold_verified_leads';
    public const REQUIREMENT_COMPLETED_PRODUCTION_TASKS = 'completed_production_tasks';

    protected $fillable = [
        'created_by',
        'title',
        'accommodation',
        'requirements',
        'requirement_type',
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

    public function scopeLabel(): string
    {
        return match ($this->reward_scope) {
            self::SCOPE_COMPANY => 'Whole Team',
            self::SCOPE_TEAM => 'Team',
            default => 'Individual',
        };
    }

    public function audienceLabel(): string
    {
        return match ($this->audience) {
            self::AUDIENCE_ALL => 'All Users',
            self::AUDIENCE_LEAD_GENERATION => 'Lead Generation',
            self::AUDIENCE_PRODUCTION => 'Production',
            default => 'Eligible Commission',
        };
    }

    public function requirementLabel(): string
    {
        return match ($this->requirement_type) {
            self::REQUIREMENT_SOLD_MINED_LEADS => 'Sold leads from mined leads',
            self::REQUIREMENT_SOLD_VERIFIED_LEADS => 'Sold leads from verified leads',
            self::REQUIREMENT_COMPLETED_PRODUCTION_TASKS => 'Completed production tasks',
            default => 'Monthly MTD',
        };
    }

    public function requirementUnitLabel(): string
    {
        return match ($this->requirement_type) {
            self::REQUIREMENT_SOLD_MINED_LEADS,
            self::REQUIREMENT_SOLD_VERIFIED_LEADS => 'sold leads',
            self::REQUIREMENT_COMPLETED_PRODUCTION_TASKS => 'completed tasks',
            default => 'MTD',
        };
    }

    public function formatsRequirementAsMoney(): bool
    {
        return ($this->requirement_type ?? self::REQUIREMENT_SALES_MTD) === self::REQUIREMENT_SALES_MTD;
    }
}
