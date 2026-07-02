<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceInclusion extends Model
{
    protected $table = 'service_items';

    protected $fillable = [
        'service_id',
        'name',
        'sort_order',
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
