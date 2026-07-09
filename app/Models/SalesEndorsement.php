<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalesEndorsement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'endorsement_code',
        'agent_id',
        'lead_id',
        'has_frankie',
        'frankie_agent_name',
        'frankie_agent_id',
        'frankie_commission_percent',
        'author_name',
        'contact_number',
        'email',
        'street_name',
        'city_state',
        'zip_code',
        'book_title',
        'isbn',
        'services',
        'service_id',
        'amount',
        'payment',
        'remarks',
        'contract_status',
        'contract_sent_at',
        'contract_signed_at',
    ];

    protected function casts(): array
    {
        return [
            'has_frankie' => 'boolean',
            'frankie_commission_percent' => 'decimal:2',
            'amount' => 'decimal:2',
            'contract_sent_at' => 'datetime',
            'contract_signed_at' => 'datetime',
        ];
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function frankieAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'frankie_agent_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function paymentRecord()
    {
        return $this->hasOne(SalesPayment::class);
    }

    public function productionProject()
    {
        return $this->hasOne(ProductionProject::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    protected static function booted(): void
    {
        static::creating(function (SalesEndorsement $endorsement) {
            if (! $endorsement->endorsement_code) {
                $endorsement->endorsement_code = static::generateEndorsementCode();
            }
        });

        static::deleting(function (SalesEndorsement $endorsement) {
            if (! $endorsement->isForceDeleting()) {
                $endorsement->paymentRecord?->delete();
            }
        });
    }

    public static function generateEndorsementCode($date = null): string
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : now();

        do {
            $code = 'SE' . $date->format('Y') . random_int(100, 999);
        } while (static::withTrashed()->where('endorsement_code', $code)->exists());

        return $code;
    }
}
