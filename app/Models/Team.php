<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    protected $fillable = [
        'brand_id',
        'department',
        'name',
        'manager_id',
        'team_leader_id',
        'description',
        'show_on_sales_dashboard',
    ];

    protected $casts = [
        'show_on_sales_dashboard' => 'boolean',
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
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
