<?php

namespace App\Mail;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Announcement $announcement,
        public readonly User $recipient
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CreatiVision CRM Announcement: ' . $this->announcement->title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.announcement',
        );
    }
}
