<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'sales_endorsement_id',
        'payment_method',
        'sold_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'sold_date' => 'date',
        ];
    }

    public function endorsement(): BelongsTo
    {
        return $this->belongsTo(SalesEndorsement::class, 'sales_endorsement_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }
}
