<?php

namespace App\Notifications;

use App\Models\Reward;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RewardUnlockedAdminNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly Reward $reward,
        private readonly User $user,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = trim($this->user->first_name . ' ' . $this->user->last_name) ?: $this->user->email;

        return [
            'title' => 'Reward unlocked',
            'message' => "{$name} unlocked {$this->reward->title}.",
            'author_name' => $name,
            'book_title' => $this->reward->title,
            'url' => route('admin.rewards.index'),
        ];
    }
}
