<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionProfile extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function rules()
    {
        return $this->hasMany(CommissionProfileRule::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
