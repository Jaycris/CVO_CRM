<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class EmailAccount extends Model
{
    protected $fillable = [
        'user_id',
        'brand_id',
        'display_name',
        'email_address',
        'username',
        'encrypted_password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'is_shared',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'imap_port' => 'integer',
            'smtp_port' => 'integer',
            'is_shared' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function messages()
    {
        return $this->hasMany(EmailMessage::class);
    }

    public function setPlainPassword(?string $password): void
    {
        if ($password !== null && $password !== '') {
            $this->encrypted_password = Crypt::encryptString($password);
        }
    }

    public function plainPassword(): ?string
    {
        if (! $this->encrypted_password) {
            return null;
        }

        return Crypt::decryptString($this->encrypted_password);
    }
}
