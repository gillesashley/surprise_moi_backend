<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Notifications\NewOrderReceived;
use Illuminate\Console\Command;

class TestSmsNotification extends Command
{
    protected $signature = 'sms:test-order-notification {order_id}';

    protected $description = 'Test sending SMS notification to vendor for an order';

    public function handle(): int
    {
        $order = Order::with(['items.orderable', 'user', 'vendor'])->find($this->argument('order_id'));

        if (! $order) {
            $this->error('Order not found.');

            return self::FAILURE;
        }

        $vendor = $order->vendor;

        if (! $vendor) {
            $this->error('No vendor found for this order.');

            return self::FAILURE;
        }

        $this->info("Vendor: {$vendor->name} ({$vendor->phone})");
        $this->info("Order: #{$order->order_number} — GHS {$order->total}");

        if (empty($vendor->phone)) {
            $this->error('Vendor has no phone number set.');

            return self::FAILURE;
        }

        $this->info('Sending SMS notification...');

        // Send the notification synchronously (bypass queue)
        $vendor->notifyNow(new NewOrderReceived($order));

        $this->info('Notification sent! The vendor should receive an SMS shortly.');

        return self::SUCCESS;
    }
}
