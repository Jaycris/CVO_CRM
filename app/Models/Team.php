<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'brand_id',
        'multiple_brands_enabled',
        'department',
        'name',
        'manager_id',
        'team_leader_id',
        'description',
        'show_on_sales_dashboard',
    ];

    protected $casts = [
        'show_on_sales_dashboard' => 'boolean',
        'multiple_brands_enabled' => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function accessibleBrands()
    {
        return $this->belongsToMany(Brand::class, 'brand_team');
    }

    public function eligibleBrandIds(): array
    {
        $ids = $this->multiple_brands_enabled ? $this->accessibleBrands->pluck('id')->all() : [];
        if ($this->brand_id) $ids[] = $this->brand_id;
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function scopeForSalesDashboard($query)
    {
        return $query->where('department', 'Sales')->where('show_on_sales_dashboard', true)
            ->where(fn ($query) => $query->whereHas('brand', fn ($query) => $query->where('is_sales_brand', true))
                ->orWhere(fn ($query) => $query->where('multiple_brands_enabled', true)
                    ->whereHas('accessibleBrands', fn ($query) => $query->where('is_sales_brand', true))));
    }

    public function teamLeader()
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function members()
    {
        return $this->hasMany(User::class);
    }
}
