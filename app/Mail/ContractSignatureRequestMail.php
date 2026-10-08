<?php

namespace App\Mail;

use App\Models\SalesEndorsement;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContractSignatureRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly SalesEndorsement $endorsement,
        public readonly string $signUrl,
        public readonly array $packet
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
            subject: $brandName . ' Contract Signature Request: ' . ($this->endorsement->book_title ?: $this->endorsement->endorsement_code),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'email.contract-signature-request',
        );
    }
}
