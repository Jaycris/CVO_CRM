<?php

namespace App\Notifications;

use App\Models\ProductionProject;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductionProjectCompletedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly ProductionProject $project)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $this->project->loadMissing('endorsement');
        $endorsement = $this->project->endorsement;

        return [
            'title' => 'Project completed',
            'message' => "Your author's/client's project is now complete.",
            'author_name' => $endorsement?->author_name,
            'book_title' => $endorsement?->book_title,
            'url' => route('production.projects.index'),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $this->project->loadMissing('endorsement', 'fulfillmentOfficer', 'tasks.assignedUser');

        return (new MailMessage)
            ->subject("Your author's/client's project is now complete")
            ->view('email.production-project-completed', [
                'project' => $this->project,
                'endorsement' => $this->project->endorsement,
                'trackerUrl' => route('production.projects.index'),
            ]);
    }
}
