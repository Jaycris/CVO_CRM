<?php

namespace App\Notifications;

use App\Models\ProductionProject;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ProductionTaskDoneNotification extends Notification
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
                'title' => "{$this->count} production tasks done",
                'message' => "{$this->count} assigned production tasks were marked done.",
                'author_name' => 'Production',
                'book_title' => 'Completed tasks',
                'url' => route('production.projects.index'),
            ];
        }

        return [
            'title' => 'Production task done',
            'message' => 'An assigned production task was marked done.',
            'author_name' => $this->project?->endorsement?->author_name,
            'book_title' => $this->project?->endorsement?->book_title,
            'url' => route('production.projects.index'),
        ];
    }

}
