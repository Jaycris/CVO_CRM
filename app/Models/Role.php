<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'department',
        'description',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissionRecords()
    {
        return $this->belongsToMany(Permission::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->relationLoaded('permissionRecords')) {
            return $this->permissionRecords->contains('key', $permission);
        }

        return $this->permissionRecords()
            ->where('key', $permission)
            ->exists();
    }
}
