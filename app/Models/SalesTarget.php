<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesTarget extends Model
{
    protected $fillable = [
        'brand_id',
        'target_month',
        'target_type',
        'user_id',
        'work_setup',
        'amount',
    ];

    protected function casts(): array
    {
        return [
            'target_month' => 'date',
            'amount' => 'decimal:2',
        ];
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
