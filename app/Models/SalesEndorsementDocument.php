<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesEndorsementDocument extends Model
{
    public const TYPE_CONTRACT = 'contract';
    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CONTRACT => 'Contract',
        self::TYPE_INVOICE => 'Invoice',
        self::TYPE_RECEIPT => 'Receipt',
        self::TYPE_OTHER => 'Others',
    ];

    protected $fillable = [
        'sales_endorsement_id',
        'uploaded_by',
        'document_type',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    public function endorsement(): BelongsTo
    {
        return $this->belongsTo(SalesEndorsement::class, 'sales_endorsement_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->document_type] ?? str($this->document_type)->title()->toString();
    }
}
