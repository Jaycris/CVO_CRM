<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class AnnouncementNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Announcement $announcement)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'New announcement',
            'message' => $this->announcement->title,
            'author_name' => 'Admin Announcement',
            'book_title' => Str::limit($this->announcement->body, 80),
            'url' => route('announcements.index'),
        ];
    }
}
