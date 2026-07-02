<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesActivity extends Model
{
    protected $fillable = [
        'brand_id',
        'sales_payment_id',
        'sales_endorsement_id',
        'lead_id',
        'agent_id',
        'lead_miner_id',
        'verifier_id',
        'service_id',
        'activity_type',
        'endorsement_code',
        'author_name',
        'book_title',
        'service_name',
        'amount',
        'payment_method',
        'payment_status',
        'sold_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'sold_date' => 'date',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalesPayment::class, 'sales_payment_id');
    }

    public function endorsement(): BelongsTo
    {
        return $this->belongsTo(SalesEndorsement::class, 'sales_endorsement_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function leadMiner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_miner_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
