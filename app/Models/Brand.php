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
        'text_color',
        'button_text_color',
        'site_logo_path',
        'is_sales_brand',
    ];

    protected function casts(): array
    {
        return [
            'is_sales_brand' => 'boolean',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
