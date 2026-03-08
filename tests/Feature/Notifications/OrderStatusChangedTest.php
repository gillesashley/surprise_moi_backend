<?php

namespace Tests\Feature\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OrderStatusChangedTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_is_notified_when_order_is_confirmed(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $customer->id]);

        $order->update(['status' => 'confirmed', 'confirmed_at' => now()]);

        Notification::assertSentTo($customer, OrderStatusChanged::class, function (OrderStatusChanged $notification) use ($order) {
            return $notification->order->id === $order->id
                && $notification->newStatus === 'confirmed';
        });
    }

    public function test_customer_is_notified_when_order_is_delivered(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'status' => 'shipped',
            'shipped_at' => now()->subDay(),
        ]);

        $order->update([
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);

        Notification::assertSentTo($customer, OrderStatusChanged::class, function (OrderStatusChanged $notification) use ($order) {
            return $notification->order->id === $order->id
                && $notification->newStatus === 'delivered';
        });
    }

    public function test_no_notification_for_non_status_changes(): void
    {
        Notification::fake();

        $customer = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $customer->id]);

        $order->update(['special_instructions' => 'Please gift wrap this']);

        Notification::assertNotSentTo($customer, OrderStatusChanged::class);
    }

    public function test_notification_data_includes_order_number(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->pending()->create(['user_id' => $customer->id]);

        $notification = new OrderStatusChanged($order, 'confirmed');
        $data = $notification->toDatabase($customer);

        $this->assertSame('order_status_changed', $data['type']);
        $this->assertSame('Order Confirmed', $data['title']);
        $this->assertStringContainsString($order->order_number, $data['message']);
        $this->assertSame('Your order #'.$order->order_number.' has been confirmed', $data['message']);
        $this->assertSame("/orders/{$order->id}", $data['action_url']);
        $this->assertNull($data['actor']);

        $this->assertArrayHasKey('subject', $data);
        $this->assertSame($order->id, $data['subject']['id']);
        $this->assertSame('order', $data['subject']['type']);
        $this->assertSame($order->order_number, $data['subject']['order_number']);
        $this->assertSame('confirmed', $data['subject']['status']);
    }
}
