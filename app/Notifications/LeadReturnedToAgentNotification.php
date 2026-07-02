<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadReturnedToAgentNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?Lead $lead = null,
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
                'title' => "{$this->count} leads returned to you",
                'message' => "{$this->count} repaired leads have been sent back to your New Leads.",
                'author_name' => 'New Leads',
                'book_title' => 'Returned leads',
                'url' => route('leads.new'),
            ];
        }

        return [
            'title' => 'Lead has returned to you',
            'message' => 'A repaired lead has been sent back to your New Leads.',
            'author_name' => $this->lead?->author_name,
            'book_title' => $this->lead?->book_title,
            'url' => route('leads.new'),
        ];
    }
}
