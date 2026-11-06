<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionDisputeOffset extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['commission_date' => 'date', 'dispute_date' => 'date', 'usd_amount' => 'decimal:2',
            'commission_snapshot' => 'array', 'voided_at' => 'datetime'];
    }

    public function adjustment()
    {
        return $this->belongsTo(CommissionAdjustment::class, 'commission_adjustment_id');
    }
}
