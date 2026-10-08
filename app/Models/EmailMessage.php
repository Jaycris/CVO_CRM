<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailMessage extends Model
{
    protected $fillable = [
        'email_account_id',
        'folder',
        'uid',
        'message_id',
        'subject',
        'from_name',
        'from_email',
        'to',
        'cc',
        'body_text',
        'body_html',
        'sent_at',
        'is_seen',
        'is_answered',
        'has_attachments',
    ];

    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'sent_at' => 'datetime',
            'is_seen' => 'boolean',
            'is_answered' => 'boolean',
            'has_attachments' => 'boolean',
        ];
    }

    public function account()
    {
        return $this->belongsTo(EmailAccount::class, 'email_account_id');
    }
}
