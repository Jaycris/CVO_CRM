<?php

namespace App\Notifications;

use App\Models\SalesEndorsement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContractSignatureRequestSentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SalesEndorsement $endorsement
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Contract sent for signature',
            'message' => 'You were CC’d on a contract signature request.',
            'author_name' => $this->endorsement->author_name,
            'book_title' => $this->endorsement->book_title,
            'endorsement_code' => $this->endorsement->endorsement_code,
            'url' => route('finance.contracts.esign', $this->endorsement),
        ];
    }
}
