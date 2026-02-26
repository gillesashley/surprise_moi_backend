<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorRejected Event
 * 
 * Fired when an admin rejects a vendor application.
 * Triggers:
 * - Email notification to the vendor with rejection reason
 * - Real-time notification via Reverb to the vendor
 * 
 * Usage: event(new VendorRejected($vendorApplication));
 */
class VendorRejected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public VendorApplication $vendorApplication) {}

    /**
     * Get the channels the event should broadcast on.
     * 
     * Broadcasts to private channel scoped to the vendor user.
     * Only the rejected vendor can subscribe.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->vendorApplication->user_id),
        ];
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'vendor_application_id' => $this->vendorApplication->id,
            'status' => 'rejected',
            'rejection_reason' => $this->vendorApplication->rejection_reason,
            'message' => 'Your vendor application has been rejected.',
            'rejected_at' => now(),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'vendor.approval.rejected';
    }
}
