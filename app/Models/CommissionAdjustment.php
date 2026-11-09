<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionAdjustment extends Model
{
    public const DEDUCTION_KINDS = ['dispute', 'refund'];

    public function isDeduction(): bool
    {
        return in_array($this->kind, self::DEDUCTION_KINDS, true);
    }

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'usd_amount' => 'decimal:2',
            'original_commission' => 'array',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(User::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}
