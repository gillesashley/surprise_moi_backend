<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReset extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The password reset URL.
     *
     * @var string
     */
    public $resetUrl;

    /**
     * The reset token (masked for logging).
     *
     * @var string
     */
    public $resetToken;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(User $user, string $resetUrl, string $resetToken)
    {
        $this->user = $user;
        $this->resetUrl = $resetUrl;
        $this->resetToken = $resetToken;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Reset Your Password',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.password-reset',
            with: [
                'user' => $this->user,
                'resetUrl' => $this->resetUrl,
                'userName' => $this->user->name ?? explode('@', $this->user->email)[0],
                'resetExpiryMinutes' => config('auth.passwords.users.expire', 60),
            ]
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
