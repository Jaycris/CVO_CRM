<?php

namespace App\Mail;

use App\Models\SalesEndorsement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSignatureCcNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SalesEndorsement $endorsement,
        public readonly array $packet,
        public readonly string $ccName,
        public readonly string $senderUserName,
        public readonly string $recipientName
    ) {
    }

    public function envelope(): Envelope
    {
        $brandName = $this->packet['brandName'] ?? $this->packet['senderName'] ?? 'VisionFlow CRM';
        $senderEmail = $this->packet['senderEmail'] ?? config('mail.from.address');

        return new Envelope(
            from: new Address($senderEmail, $brandName),
            replyTo: [
                new Address($senderEmail, $brandName),
            ],
            subject: 'Your signing request has been successfully sent!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.contract-signature-cc-notification',
        );
    }
}
