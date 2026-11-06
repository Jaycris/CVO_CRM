<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionRelease extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['released_at' => 'date', 'commission_breakdown' => 'array'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public static function latestFor(int $paymentId, int $agentId): ?self
    {
        return static::where('sales_payment_id', $paymentId)->where('agent_id', $agentId)->latest('id')->first();
    }
}
