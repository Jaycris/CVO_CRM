<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionAdjustmentReconciliation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before_state' => 'array', 'after_state' => 'array'];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
