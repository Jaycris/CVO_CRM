<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionProjectsEndorsedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly int $count,
        private readonly string $trackerType
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $trackerLabel = str($this->trackerType)->title()->toString();
        $projectLabel = $this->count === 1 ? 'Project' : 'Projects';

        return [
            'title' => "{$this->count} New {$projectLabel} for {$trackerLabel}",
            'message' => "{$this->count} new {$projectLabel} for {$trackerLabel} has been endorsed.",
            'author_name' => 'Production',
            'book_title' => "{$trackerLabel} endorsement",
            'url' => route('production.projects.index', ['tracker' => $this->trackerType]),
        ];
    }
}
