<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $confirmationUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Confirm your Surprise moi account deletion',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.account-deletion-confirm',
            with: [
                'user' => $this->user,
                'confirmationUrl' => $this->confirmationUrl,
                'userName' => $this->user->name ?? explode('@', $this->user->email)[0],
            ]
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
