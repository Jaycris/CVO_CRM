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
        private readonly int $count = 1,
        private readonly array $leadIds = []
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $leadIds = $this->leadIds ?: ($this->lead ? [$this->lead->id] : []);

        if ($this->count > 1) {
            return [
                'notification_key' => 'verification_queue_assigned',
                'verification_assigned_to' => $notifiable->id ?? null,
                'lead_id' => null,
                'lead_ids' => $leadIds,
                'title' => "{$this->count} new leads for verification",
                'message' => "{$this->count} new leads for verification have been assigned to you.",
                'author_name' => 'Verification Queue',
                'book_title' => 'New assignments',
                'url' => route('leads.verification-queue'),
            ];
        }

        return [
            'notification_key' => 'verification_queue_assigned',
            'verification_assigned_to' => $notifiable->id ?? null,
            'lead_id' => $this->lead?->id,
            'lead_ids' => $leadIds,
            'title' => 'New lead for verification',
            'message' => 'A new lead for verification has been assigned to you.',
            'author_name' => $this->lead?->author_name,
            'book_title' => $this->lead?->book_title,
            'url' => route('leads.verification-queue'),
        ];
    }
}
