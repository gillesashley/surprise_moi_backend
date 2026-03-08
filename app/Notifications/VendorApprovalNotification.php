<?php

namespace App\Notifications;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

/**
 * VendorApprovalNotification
 *
 * Handles both email and real-time Reverb notifications for vendor approval status changes.
 * Sent via three channels:
 * 1. Database - Persisted notification for in-app notification list
 * 2. Mail - Traditional email notification with detailed information
 * 3. Broadcast - Real-time notification via Laravel Reverb WebSocket
 *
 * Usage:
 *   $user->notify(new VendorApprovalNotification($vendorApplication, 'approved'));
 *   $user->notify(new VendorApprovalNotification($vendorApplication, 'rejected'));
 */
class VendorApprovalNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Notification status: 'approved' or 'rejected'
     */
    protected string $status;

    /**
     * Create a new notification instance.
     *
     * @param  string  $status  'approved' or 'rejected'
     */
    public function __construct(
        public VendorApplication $vendorApplication,
        string $status = 'approved'
    ) {
        $this->status = $status;
        $this->queue = 'notifications';
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database', 'mail', BroadcastChannel::class];

        if ($notifiable->deviceTokens()->exists()) {
            $channels[] = FcmChannel::class;
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->status === 'approved') {
            return $this->buildApprovedEmail($notifiable);
        }

        return $this->buildRejectedEmail($notifiable);
    }

    /**
     * Build the approval email.
     */
    protected function buildApprovedEmail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Vendor Account Has Been Approved ✅')
            ->greeting("Hello {$notifiable->name},")
            ->line('Great news!')
            ->line('Your vendor account on Surprise Moi has been successfully approved. You can now log in and start selling on the platform.')
            ->line('You can now:')
            ->line('• Upload your products')
            ->line('• Manage your shop')
            ->line('• Receive and fulfill orders')
            ->line('• Chat with customers')
            ->action('Open the App', config('deep_links.share_base_url'))
            ->line("We're excited to have you as part of the Surprise Moi marketplace.")
            ->salutation('Best regards, The Surprise Moi Team');
    }

    /**
     * Build the rejection email.
     */
    protected function buildRejectedEmail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('Vendor Application Update')
            ->greeting("Hello {$notifiable->name},")
            ->line('Thank you for applying to become a vendor on Surprise Moi.');

        $message->line('Unfortunately, your application was not approved at this time.');

        if ($this->vendorApplication->rejection_reason) {
            $message->line('**Reason:** '.$this->vendorApplication->rejection_reason);
        }

        $message
            ->line('Please update the required details and resubmit your application.')
            ->action('Open the App', config('deep_links.share_base_url'))
            ->line('If you have questions, please contact our support team.')
            ->salutation('Best regards, The Surprise Moi Team');

        return $message;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $isApproved = $this->status === 'approved';

        return [
            'type' => 'vendor_'.$this->status,
            'title' => $isApproved ? 'Application Approved' : 'Application Rejected',
            'message' => $isApproved
                ? 'Your Surprise Moi vendor account is approved. Upload products and start selling.'
                : 'Your vendor application was not approved. Please update the required details and resubmit.',
            'action_url' => '/dashboard/vendor-applications/'.$this->vendorApplication->id,
            'actor' => null,
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => $this->status,
            ],
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        if ($this->status === 'approved') {
            $title = 'Application Approved';
            $body = 'Your Surprise Moi vendor account is approved. Upload products and start selling.';
        } else {
            $title = 'Application Rejected';
            $body = 'Your vendor application was not approved. Please update the required details and resubmit.';
        }

        return new BroadcastMessage([
            'title' => $title,
            'body' => $body,
            'status' => $this->status,
            'vendor_application_id' => $this->vendorApplication->id,
            'rejection_reason' => $this->vendorApplication->rejection_reason,
            'action_url' => '/dashboard/vendor-applications/'.$this->vendorApplication->id,
        ]);
    }

    /**
     * Get the FCM representation of the notification.
     */
    public function toFcm(object $notifiable): FcmMessage
    {
        $data = $this->toDatabase($notifiable);

        return FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title($data['title'])
                    ->body($data['message'])
            )
            ->data([
                'type' => $data['type'],
                'action_url' => $data['action_url'],
            ]);
    }
}
