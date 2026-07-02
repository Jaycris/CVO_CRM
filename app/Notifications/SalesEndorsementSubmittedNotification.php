<?php

namespace App\Notifications;

use App\Models\SalesEndorsement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SalesEndorsementSubmittedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?SalesEndorsement $endorsement = null,
        private readonly int $count = 1
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->count > 1) {
            return [
                'title' => "{$this->count} new Sales Endorsements submitted",
                'message' => "{$this->count} Sales Endorsements are ready for Finance review.",
                'author_name' => 'Finance Review',
                'book_title' => 'New submissions',
                'count' => $this->count,
                'url' => route('sales.endorsements.index'),
            ];
        }

        return [
            'title' => 'New Sales Endorsement submitted',
            'message' => 'A Sales Endorsement is ready for Finance review.',
            'author_name' => $this->endorsement?->agent?->first_name
                ? trim($this->endormentAgentName())
                : 'Sales Agent',
            'book_title' => $this->endorsement?->book_title ?? 'New submission',
            'count' => 1,
            'url' => route('sales.endorsements.index'),
        ];
    }

    private function endormentAgentName(): string
    {
        return ($this->endorsement?->agent?->first_name ?? '') . ' ' . ($this->endorsement?->agent?->last_name ?? '');
    }
}
