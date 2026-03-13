<?php

namespace App\Notifications;

use App\Models\TierUpgradeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TierUpgradeApprovedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('Tier 1 Upgrade Approved!')
            ->greeting("Hello {$notifiable->name},")
            ->line('Congratulations! Your Tier 1 upgrade has been approved.')
            ->line('You are now a Registered Business vendor on SurpriseMoi.')
            ->salutation('Best regards, The SurpriseMoi Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'tier_upgrade_approved',
            'title' => 'Tier 1 Upgrade Approved',
            'message' => 'Your Tier 1 upgrade has been approved. You are now a Registered Business vendor.',
            'subject' => [
                'type' => 'tier_upgrade_request',
                'id' => $this->upgradeRequest->id,
            ],
        ];
    }
}
