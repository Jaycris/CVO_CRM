<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class BrandMailSetting extends Model
{
    protected $fillable = ['brand_id', 'is_enabled', 'display_name', 'email_address', 'reply_to_address',
        'username', 'smtp_host', 'smtp_port', 'smtp_encryption'];

    protected $hidden = ['encrypted_password'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean', 'smtp_port' => 'integer'];
    }

    public function setPlainPassword(?string $password): void
    {
        if ($password !== null && $password !== '') $this->encrypted_password = Crypt::encryptString($password);
    }

    public function plainPassword(): ?string
    {
        return $this->encrypted_password ? Crypt::decryptString($this->encrypted_password) : null;
    }
}
