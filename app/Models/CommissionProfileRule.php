<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionProfileRule extends Model
{
    protected $fillable = [
        'commission_profile_id',
        'minimum_mtd_percent',
        'commission_percent',
    ];

    protected function casts(): array
    {
        return [
            'minimum_mtd_percent' => 'decimal:2',
            'commission_percent' => 'decimal:2',
        ];
    }

    public function profile()
    {
        return $this->belongsTo(CommissionProfile::class, 'commission_profile_id');
    }
}
