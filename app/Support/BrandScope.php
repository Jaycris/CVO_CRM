<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BrandScope
{
    public const PARENT_BRAND = 'CreatiVision Outsourcing';

    public static function canAccessAllBrands(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->role?->name === 'Admin') {
            return true;
        }

        return $user->brand?->imprint_name === self::PARENT_BRAND;
    }

    public static function apply(Builder $query, ?User $user, string $column = 'brand_id'): Builder
    {
        if (self::canAccessAllBrands($user)) {
            return $query;
        }

        return $query->where($column, $user?->brand_id);
    }

    public static function parentBrandId(): ?int
    {
        return Brand::where('imprint_name', self::PARENT_BRAND)->value('id');
    }

    public static function userBrandId(?User $user): ?int
    {
        return $user?->brand_id ?: self::parentBrandId();
    }
}
