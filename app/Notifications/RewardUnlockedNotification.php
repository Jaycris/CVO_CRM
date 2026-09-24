<?php

namespace App\Notifications;

use App\Models\Reward;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class RewardUnlockedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Reward $reward)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Reward unlocked',
            'message' => "You unlocked {$this->reward->title}. You can now request to claim it.",
            'author_name' => 'Rewards',
            'book_title' => $this->reward->title,
            'url' => route('rewards.claim.show', $this->reward),
        ];
    }
}
