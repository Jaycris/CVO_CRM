<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EndOfShiftReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_to_user_id',
        'report_to_hris_employee_id',
        'shift_date',
        'work_done',
        'pending_work',
        'blockers',
        'notes',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'shift_date' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reportToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'report_to_user_id');
    }
}
