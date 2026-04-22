<?php

namespace App\Events;

use App\Models\VendorApplication;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * VendorApprovalSubmitted Event
 *
 * Fired when a vendor submits their application for approval.
 * Notifies all admins in real-time via Reverb WebSocket.
 *
 * Triggers:
 * - Real-time notification to admin users via Reverb
 * - Admin dashboard updates with pending applications
 */
class VendorApprovalSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public VendorApplication $vendorApplication) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts to private 'admin' channel.
     * Only authenticated admin users can subscribe (see routes/channels.php).
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin'),
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
            'user_id' => $this->vendorApplication->user_id,
            'user_name' => $this->vendorApplication->user->name,
            'user_email' => $this->vendorApplication->user->email,
            'submitted_at' => $this->vendorApplication->submitted_at,
            'message' => "New vendor application submitted by {$this->vendorApplication->user->name}",
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'vendor.approval.submitted';
    }
}
