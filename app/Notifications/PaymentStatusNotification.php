<?php

namespace App\Notifications;

use App\Models\SalesEndorsement;
use App\Models\SalesPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PaymentStatusNotification extends Notification
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
        $status = $this->payment->status;
        $title = match ($status) {
            'Payment Success' => 'Client Sold',
            'Refund' => 'Client Refund',
            'Dispute' => 'Client Dispute',
            'Declined' => 'Client Payment Declined',
            'Processing' => 'Client Payment Processing',
            default => 'Client Payment Updated',
        };

        $message = match ($status) {
            'Payment Success' => 'Finance confirmed this client as paid.',
            'Refund' => 'Finance recorded this client as refunded.',
            'Dispute' => 'Finance recorded a dispute for this client.',
            'Declined' => 'Finance recorded this client payment as declined.',
            'Processing' => 'Finance marked this client payment as processing.',
            default => 'Finance updated this client payment status.',
        };

        $url = match ($status) {
            'Payment Success' => route('sales.sold'),
            'Refund', 'Dispute' => route('sales.refunds'),
            default => route('sales.endorsements.index'),
        };

        return [
            'title' => $title,
            'message' => $message,
            'author_name' => $this->endorsement->author_name,
            'book_title' => $this->endorsement->book_title,
            'amount' => (float) $this->endorsement->amount,
            'payment_method' => $this->payment->payment_method,
            'payment_status' => $status,
            'sold_date' => $this->payment->sold_date?->format('M d, Y'),
            'url' => $url,
        ];
    }
}
