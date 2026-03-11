<?php

namespace App\Notifications;

use App\Models\VendorApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * VendorOnboardingPaidNotification
 *
 * Sent to admin users when a vendor completes their onboarding payment.
 * Prompts admins to review the new vendor application on the dashboard.
 */
class VendorOnboardingPaidNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VendorApplication $vendorApplication)
    {
        $this->queue = 'notifications';
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $vendorName = $this->vendorApplication->user->name;
        $tier = $this->vendorApplication->has_business_certificate ? 'Tier 1 (Business)' : 'Tier 2 (Individual)';

        return (new MailMessage)
            ->subject('New Vendor Onboarding Payment Received')
            ->greeting("Hello {$notifiable->name},")
            ->line('A vendor has completed their onboarding payment and is awaiting review.')
            ->line("**Vendor:** {$vendorName}")
            ->line("**Tier:** {$tier}")
            ->line("**Amount Paid:** GHS {$this->vendorApplication->onboarding_fee}")
            ->action('Review Application', url('/dashboard/vendor-applications'))
            ->line('Please review this application at your earliest convenience.')
            ->salutation('Best regards, The Surprise Moi Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'vendor_onboarding_paid',
            'title' => 'New Vendor Payment Received',
            'message' => $this->vendorApplication->user->name.' has completed their onboarding payment and is awaiting review.',
            'action_url' => '/dashboard/vendor-applications',
            'actor' => [
                'id' => $this->vendorApplication->user_id,
                'name' => $this->vendorApplication->user->name,
            ],
            'subject' => [
                'id' => $this->vendorApplication->id,
                'type' => 'vendor_application',
                'status' => $this->vendorApplication->status,
            ],
        ];
    }
}
