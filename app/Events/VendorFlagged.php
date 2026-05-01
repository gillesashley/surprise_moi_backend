<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorFlagged Event
 *
 * Fired when an admin flags a vendor application for missing or unclear details.
 * Notifies all admins in real-time so the dashboard reflects the change.
 *
 * Triggers:
 * - Real-time notification to admin users via Reverb on the 'admin' channel
 * - Admin dashboard updates with the new flagged status
 */
class VendorFlagged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public VendorApplication $vendorApplication) {}

    /**
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'vendor_application_id' => $this->vendorApplication->id,
            'user_id' => $this->vendorApplication->user_id,
            'user_name' => $this->vendorApplication->user->name,
            'user_email' => $this->vendorApplication->user->email,
            'flag_reason' => $this->vendorApplication->flag_reason,
            'flagged_at' => $this->vendorApplication->flagged_at?->toIso8601String(),
            'grace_period_ends_at' => $this->vendorApplication->grace_period_ends_at?->toIso8601String(),
            'message' => "Vendor application flagged for {$this->vendorApplication->user->name}",
        ];
    }

    public function broadcastAs(): string
    {
        return 'vendor.flagged';
    }
}
