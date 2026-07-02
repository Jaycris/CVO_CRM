<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadAssignedNotification extends Notification
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
                'title' => "{$this->count} leads assigned to you",
                'message' => "{$this->count} leads have been assigned to you.",
                'author_name' => 'Sales Leads',
                'book_title' => 'New assigned leads',
                'url' => route('leads.new'),
            ];
        }

        return [
            'title' => 'New lead assigned',
            'message' => 'A lead has been assigned to you.',
            'author_name' => $this->lead?->author_name,
            'book_title' => $this->lead?->book_title,
            'url' => route('leads.new'),
        ];
    }
}
