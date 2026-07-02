<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'created_by',
        'publisher',
        'book_title',
        'author_name',
        'phone_numbers',
        'phone_number_statuses',
        'email',
        'book_link',
        'published_date',
        'assigned_to',
        'assigned_date',
        'previous_agent_id',
        'previous_agent_released_at',
        'previous_agent_release_reason',
        'verify_score',
        'author_confirmed',
        'book_confirmed',
        'phone_confirmed',
        'verified_phone_numbers',
        'email_confirmed',
        'linkedin_matched',
        'verification_notes',
        'verified_at',
        'verified_by',
        'returned_at',
        'returned_by',
        'return_notes',
        'sales_stage',
        'sales_stage_updated_at',
        'archived_at',
        'archived_by',
        'lead_generation_stage',
        'verification_assigned_to',
    ];

    protected function casts(): array
    {
        return [
            'phone_numbers' => 'array',
            'phone_number_statuses' => 'array',
            'published_date' => 'date',
            'assigned_date' => 'date',
            'previous_agent_released_at' => 'datetime',
            'author_confirmed' => 'boolean',
            'book_confirmed' => 'boolean',
            'phone_confirmed' => 'boolean',
            'verified_phone_numbers' => 'array',
            'email_confirmed' => 'boolean',
            'linkedin_matched' => 'boolean',
            'verified_at' => 'datetime',
            'returned_at' => 'datetime',
            'sales_stage_updated_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function previousAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'previous_agent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function verificationAssignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verification_assigned_to');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function assignmentHistories(): HasMany
    {
        return $this->hasMany(LeadAssignmentHistory::class)->latest('assigned_at');
    }
}
