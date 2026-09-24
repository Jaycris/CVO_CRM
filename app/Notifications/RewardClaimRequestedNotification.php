<?php

namespace App\Notifications;

use App\Models\RewardUnlock;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RewardClaimRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly RewardUnlock $unlock)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $this->unlock->loadMissing('reward', 'user');
        $user = $this->unlock->user;
        $name = trim(($user?->first_name ?? '') . ' ' . ($user?->last_name ?? '')) ?: $user?->email;

        return [
            'title' => 'Reward claim requested',
            'message' => "{$name} requested to claim {$this->unlock->reward?->title}.",
            'author_name' => $name,
            'book_title' => $this->unlock->reward?->title,
            'url' => route('admin.rewards.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->unlock->loadMissing('reward', 'user');

        return (new MailMessage)
            ->subject('Reward claim requested')
            ->view('email.reward-claim-requested', [
                'unlock' => $this->unlock,
                'reward' => $this->unlock->reward,
                'user' => $this->unlock->user,
                'rewardsUrl' => route('admin.rewards.index'),
            ]);
    }
}
