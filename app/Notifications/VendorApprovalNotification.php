<?php

namespace App\Notifications;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Channels\BroadcastChannel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * VendorApprovalNotification
 * 
 * Handles both email and real-time Reverb notifications for vendor approval status changes.
 * Sent via two channels:
 * 1. Mail - Traditional email notification with detailed information
 * 2. Broadcast - Real-time notification via Laravel Reverb WebSocket
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
     *
     * @var string
     */
    protected string $status;

    /**
     * Create a new notification instance.
     *
     * @param VendorApplication $vendorApplication
     * @param string $status 'approved' or 'rejected'
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
        return ['mail', BroadcastChannel::class];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
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
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildApprovedEmail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 Your Vendor Application Has Been Approved!')
            ->greeting("Hello {$notifiable->name},")
            ->line('Congratulations! Your vendor application has been approved.')
            ->line('You can now start selling on SurpriseMoi!')
            ->line('Application ID: ' . $this->vendorApplication->id)
            ->action('Go to Dashboard', url('/dashboard'))
            ->line('If you have any questions, feel free to contact our support team.')
            ->salutation('Best regards, SurpriseMoi Team');
    }

    /**
     * Build the rejection email.
     *
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    protected function buildRejectedEmail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject('❌ Vendor Application Status Update')
            ->greeting("Hello {$notifiable->name},")
            ->line('Thank you for applying to become a vendor on SurpriseMoi.')
            ->line('Unfortunately, your application has been rejected.');

        if ($this->vendorApplication->rejection_reason) {
            $message->line('**Reason:** ' . $this->vendorApplication->rejection_reason);
        }

        $message
            ->line('Application ID: ' . $this->vendorApplication->id)
            ->line('You can reapply after addressing the feedback provided.')
            ->action('View Application', url('/dashboard/vendor-applications/' . $this->vendorApplication->id))
            ->line('If you have questions about the decision, please contact our support team.')
            ->salutation('Best regards, SurpriseMoi Team');

        return $message;
    }

    /**
     * Get the broadcastable representation of the notification.
     *
     * @return \Illuminate\Notifications\Messages\BroadcastMessage
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        if ($this->status === 'approved') {
            $title = '✅ Application Approved';
            $body = 'Congratulations! Your vendor application has been approved.';
        } else {
            $title = '❌ Application Rejected';
            $body = 'Your vendor application has been rejected.';
        }

        return new BroadcastMessage([
            'title' => $title,
            'body' => $body,
            'status' => $this->status,
            'vendor_application_id' => $this->vendorApplication->id,
            'rejection_reason' => $this->vendorApplication->rejection_reason,
            'action_url' => '/dashboard/vendor-applications/' . $this->vendorApplication->id,
        ]);
    }
}
