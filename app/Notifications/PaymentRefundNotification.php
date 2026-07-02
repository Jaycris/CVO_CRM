<?php

namespace App\Notifications;

use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentRefundNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SalesEndorsement $endorsement,
        private readonly SalesPayment $payment
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Client Refund',
            'message' => 'Finance recorded this client as refunded.',
            'author_name' => $this->endorsement->author_name,
            'book_title' => $this->endorsement->book_title,
            'amount' => (float) $this->endorsement->amount,
            'payment_method' => $this->payment->payment_method,
            'sold_date' => $this->payment->sold_date?->format('M d, Y'),
            'url' => route('sales.refunds'),
        ];
    }
}
