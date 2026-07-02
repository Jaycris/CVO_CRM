<?php

namespace App\Mail;

use App\Models\Brand;
use App\Models\User;
use App\Support\BrandScope;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public ?Brand $brand;
    public string $invitationUrl;

    public function __construct(User $user)
    {
        $this->user = $user->loadMissing('brand');
        $this->brand = $this->user->brand
            ?? Brand::where('imprint_name', BrandScope::PARENT_BRAND)->first();

        $this->invitationUrl = URL::temporarySignedRoute(
            'invitation.password.create',
            now()->addDays(7),
            ['user' => $user->id]
        );
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Create Your CreatiVision CRM Password',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'email.user-invitation',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
