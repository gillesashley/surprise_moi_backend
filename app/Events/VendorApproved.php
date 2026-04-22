<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorApproved Event
 *
 * Fired when an admin approves a vendor application.
 * Triggers:
 * - Email notification to the vendor
 * - Real-time notification via Reverb to the vendor
 *
 * Usage: event(new VendorApproved($vendorApplication));
 */
class VendorApproved implements ShouldBroadcast
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
     * Only the approved vendor can subscribe.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.'.$this->vendorApplication->user_id),
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
            'status' => 'approved',
            'message' => 'Congratulations! Your vendor application has been approved.',
            'approved_at' => now(),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'vendor.approval.approved';
    }
}
