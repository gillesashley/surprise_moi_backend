<?php

namespace App\Notifications;

use App\Models\TierUpgradeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TierUpgradeRejectedNotification extends Notification implements ShouldQueue
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
            ->subject('Tier 1 Upgrade Request Rejected')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your Tier 1 upgrade request has been rejected.')
            ->line('Reason: '.$this->upgradeRequest->admin_notes)
            ->line('You can resubmit your business certificate document to try again.')
            ->salutation('Best regards, The SurpriseMoi Team');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'tier_upgrade_rejected',
            'title' => 'Tier 1 Upgrade Rejected',
            'message' => 'Your Tier 1 upgrade has been rejected: '.$this->upgradeRequest->admin_notes,
            'subject' => [
                'type' => 'tier_upgrade_request',
                'id' => $this->upgradeRequest->id,
            ],
        ];
    }
}
