<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'name',
        'category',
        'price',
        'description',
        'pdf_path',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function inclusions()
    {
        return $this->hasMany(ServiceInclusion::class)->orderBy('sort_order');
    }

    protected static function booted(): void
    {
        static::forceDeleted(function (Service $service) {
            if ($service->pdf_path) {
                Storage::disk('public')->delete($service->pdf_path);
            }
        });
    }
}
