<?php

namespace App\Notifications;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * VendorApplicationSubmittedNotification
 *
 * Sent to the vendor when they submit their application for review.
 * Channels: email + database only (no push for submission confirmation).
 *
 * Usage:
 *   $user->notify(new VendorApplicationSubmittedNotification($vendorApplication));
 */
class VendorApplicationSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Vendor Application Received')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your vendor registration has been received. We will review your application and notify you once it is approved.')
            ->line('This process usually takes a short while. We appreciate your patience.')
            ->line('If you have any questions in the meantime, feel free to contact our support team.')
            ->salutation('Best regards, The Surprise Moi Team');
    }

    /**
     * Get the database representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_application_submitted',
            'title' => 'Application Received',
            'message' => 'Your vendor registration has been received. We will review and notify you once approved.',
            'action_url' => '/vendor-applications/'.$this->vendorApplication->id,
            'actor' => null,
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => 'pending',
            ],
        ];
    }
}
