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

        return ! $user->multiple_brands_enabled && $user->brand?->imprint_name === self::PARENT_BRAND;
    }

    public static function allowedBrandIds(?User $user): array
    {
        if (! $user) return [];
        $ids = $user->multiple_brands_enabled ? $user->accessibleBrands->pluck('id')->all() : [];
        if ($user->brand_id) $ids[] = $user->brand_id;
        return array_values(array_unique(array_map('intval', $ids)));
    }

    public static function canAccessBrand(?User $user, $brandId): bool
    {
        return $brandId !== null && (self::canAccessAllBrands($user)
            || in_array((int) $brandId, self::allowedBrandIds($user), true));
    }

    // Employee eligibility is independent of permission to view other employees' records.
    public static function applyToUsers(Builder $query, array $brandIds): Builder
    {
        return $query->where(function ($query) use ($brandIds) {
            $query->whereIn('brand_id', $brandIds)
                ->orWhere(fn ($query) => $query->where('multiple_brands_enabled', true)
                    ->whereHas('accessibleBrands', fn ($query) => $query->whereIn('brands.id', $brandIds)))
                ->orWhere(fn ($query) => $query->where('multiple_brands_enabled', false)
                    ->whereHas('brand', fn ($query) => $query->where('imprint_name', self::PARENT_BRAND)));
        });
    }

    public static function apply(Builder $query, ?User $user, string $column = 'brand_id'): Builder
    {
        if (self::canAccessAllBrands($user)) {
            return $query;
        }

        if ($query->getModel() instanceof User && $column === 'brand_id') {
            return self::applyToUsers($query, self::allowedBrandIds($user));
        }
        return $query->whereIn($column, self::allowedBrandIds($user));
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
