<?php

namespace App\Notifications;

use App\Models\Lead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class LeadAutoReturnedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?Lead $lead = null,
        private readonly int $count = 1,
        private readonly bool $managerNotice = false
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->managerNotice) {
            return $this->managerPayload();
        }

        return $this->agentPayload();
    }

    private function agentPayload(): array
    {
        if ($this->count > 1) {
            return [
                'title' => "{$this->count} untouched leads returned",
                'message' => "{$this->count} leads were returned to Ready to Assign because no sales activity was recorded before the auto-return timer.",
                'author_name' => 'Auto Return',
                'book_title' => 'Ready to Assign',
                'url' => route('dashboard'),
            ];
        }

        return [
            'title' => 'Untouched lead returned',
            'message' => 'A lead was returned to Ready to Assign because no sales activity was recorded before the auto-return timer.',
            'author_name' => $this->lead?->author_name,
            'book_title' => $this->lead?->book_title,
            'url' => route('dashboard'),
        ];
    }

    private function managerPayload(): array
    {
        if ($this->count > 1) {
            return [
                'title' => "{$this->count} untouched leads returned",
                'message' => "{$this->count} untouched assigned leads are back in Unassigned Leads / Ready to Assign.",
                'author_name' => 'Unassigned Leads',
                'book_title' => 'Ready to Assign',
                'url' => route('leads.unassigned'),
            ];
        }

        return [
            'title' => 'Untouched lead returned',
            'message' => 'An untouched assigned lead is back in Unassigned Leads / Ready to Assign.',
            'author_name' => $this->lead?->author_name,
            'book_title' => $this->lead?->book_title,
            'url' => route('leads.unassigned'),
        ];
    }
}
