<?php

namespace App\Notifications;

use App\Models\TierUpgradeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TierUpgradeSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TierUpgradeRequest $upgradeRequest)
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
        $vendorName = $this->upgradeRequest->vendor->name ?? 'A vendor';

        return (new MailMessage)
            ->subject('New Tier Upgrade Request')
            ->greeting('Hello Admin,')
            ->line("{$vendorName} has submitted a tier upgrade request for review.")
            ->line('Please review the submitted business certificate document.')
            ->salutation('Best regards, The SurpriseMoi System');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $vendorName = $this->upgradeRequest->vendor->name ?? 'A vendor';

        return [
            'type' => 'tier_upgrade_submitted',
            'title' => 'New Tier Upgrade Request',
            'message' => "{$vendorName} has submitted a tier upgrade request for review.",
            'subject' => [
                'type' => 'tier_upgrade_request',
                'id' => $this->upgradeRequest->id,
            ],
            'actor' => [
                'type' => 'user',
                'id' => $this->upgradeRequest->vendor_id,
            ],
        ];
    }
}
