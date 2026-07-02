<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'imprint_name',
        'crm_display_name',
        'description',
        'address',
        'logo_path',
        'primary_color',
        'accent_color',
        'site_logo_path',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
