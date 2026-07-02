<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadSentToVerificationNotification extends Notification
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
                'title' => "{$this->count} leads for verification",
                'message' => "{$this->count} leads are waiting in your Verification Queue.",
                'author_name' => 'Verification Queue',
                'book_title' => 'New leads to verify',
                'url' => route('leads.verification-queue'),
            ];
        }

        return [
            'title' => 'Lead for verification',
            'message' => 'You have a lead waiting in the Verification Queue.',
            'author_name' => $this->lead?->author_name,
            'book_title' => $this->lead?->book_title,
            'url' => route('leads.verification-queue'),
        ];
    }
}
