<?php

namespace App\Notifications;

use App\Models\ProductionProject;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionTaskAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly ?ProductionProject $project = null,
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
                'title' => "{$this->count} production tasks assigned to you",
                'message' => "{$this->count} production tasks have been assigned to you.",
                'author_name' => 'Production',
                'book_title' => 'New assigned tasks',
                'url' => route('production.tasks.index'),
            ];
        }

        return [
            'title' => 'New production task assigned',
            'message' => 'A production task has been assigned to you.',
            'author_name' => $this->project?->endorsement?->author_name,
            'book_title' => $this->project?->endorsement?->book_title,
            'url' => route('production.tasks.index'),
        ];
    }
}
