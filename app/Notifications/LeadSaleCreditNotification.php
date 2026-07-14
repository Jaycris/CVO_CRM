<?php

namespace App\Notifications;

use App\Models\SalesPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadSaleCreditNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $creditType,
        private readonly ?SalesPayment $payment = null,
        private readonly int $count = 1
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $isVerifiedCredit = $this->creditType === 'verified';
        $route = $isVerifiedCredit ? 'reports.verified-sold' : 'reports.sold-mined';
        $status = $this->payment?->status ?? 'Payment Success';
        $creditLabel = $isVerifiedCredit ? 'verified' : 'mined';
        $singularTitle = match ($status) {
            'Refund' => "Your {$creditLabel} lead became a refund",
            'Dispute' => "Your {$creditLabel} lead has a dispute",
            default => "Your {$creditLabel} lead was sold",
        };
        $pluralTitle = match ($status) {
            'Refund' => "{$this->count} {$creditLabel} leads became refunds",
            'Dispute' => "{$this->count} {$creditLabel} leads have disputes",
            default => "{$this->count} {$creditLabel} leads were sold",
        };
        $pluralMessage = match ($status) {
            'Refund' => "{$this->count} sold leads you {$creditLabel} were marked as refunds.",
            'Dispute' => "{$this->count} sold leads you {$creditLabel} were marked as disputes.",
            default => "{$this->count} leads you {$creditLabel} now have successful payments.",
        };
        $bookTitle = match ($status) {
            'Refund' => 'Refunded sold leads',
            'Dispute' => 'Disputed sold leads',
            default => 'New successful sales',
        };

        if ($this->count > 1) {
            return [
                'title' => $pluralTitle,
                'message' => $pluralMessage,
                'author_name' => $isVerifiedCredit ? 'Verified Sold Leads' : 'Sold Leads',
                'book_title' => $bookTitle,
                'credit_type' => $this->creditType,
                'payment_status' => $status,
                'count' => $this->count,
                'url' => route($route),
            ];
        }

        return [
            'title' => $singularTitle,
            'message' => match ($status) {
                'Refund' => "A sold lead you {$creditLabel} was marked as a refund.",
                'Dispute' => "A sold lead you {$creditLabel} was marked as a dispute.",
                default => "A lead you {$creditLabel} now has a successful payment.",
            },
            'author_name' => $isVerifiedCredit ? 'Verified Sold Lead' : 'Sold Lead',
            'book_title' => $bookTitle,
            'credit_type' => $this->creditType,
            'payment_status' => $status,
            'count' => 1,
            'url' => route($route),
        ];
    }
}
