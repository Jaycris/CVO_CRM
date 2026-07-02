<?php

namespace App\Models;

use App\Support\BrandScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class DashboardBanner extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'brand_id',
        'created_by',
        'type',
        'title',
        'message',
        'button_text',
        'button_url',
        'image_path',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeCurrentlyVisible(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopeVisibleFor(Builder $query, ?User $user): Builder
    {
        if (BrandScope::canAccessAllBrands($user)) {
            return $query;
        }

        $parentBrandId = BrandScope::parentBrandId();

        return $query->where(function (Builder $query) use ($user, $parentBrandId) {
            $query->where('brand_id', $user?->brand_id);

            if ($parentBrandId) {
                $query->orWhere('brand_id', $parentBrandId);
            }
        });
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
