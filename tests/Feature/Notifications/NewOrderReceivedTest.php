<?php

namespace Tests\Feature\Notifications;

use App\Models\DeviceToken;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\NewOrderReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

class NewOrderReceivedTest extends TestCase
{
    use RefreshDatabase;

    public function test_notification_contains_correct_order_details(): void
    {
        $customer = User::factory()->create(['name' => 'John Buyer']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
            'total' => 150.50,
        ]);

        $product = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Birthday Cake',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product->id,
        ]);

        $order->load(['items.orderable', 'user']);

        $notification = new NewOrderReceived($order);
        $data = $notification->toDatabase($vendor);

        $this->assertEquals('new_order', $data['type']);
        $this->assertEquals('New Order Received', $data['title']);
        $this->assertStringContainsString('John Buyer', $data['message']);
        $this->assertStringContainsString('Birthday Cake', $data['message']);
        $this->assertStringContainsString('150.50', $data['message']);
        $this->assertEquals("/orders/{$order->id}", $data['action_url']);
    }

    public function test_notification_includes_fcm_channel_when_vendor_has_device_tokens(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        DeviceToken::create([
            'user_id' => $vendor->id,
            'token' => 'fake-fcm-token',
            'platform' => 'android',
        ]);

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $order->load(['items.orderable', 'user']);

        $notification = new NewOrderReceived($order);
        $channels = $notification->via($vendor);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_notification_excludes_fcm_channel_when_vendor_has_no_device_tokens(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $order->load(['items.orderable', 'user']);

        $notification = new NewOrderReceived($order);
        $channels = $notification->via($vendor);

        $this->assertContains('database', $channels);
        $this->assertContains('broadcast', $channels);
        $this->assertNotContains(FcmChannel::class, $channels);
    }

    public function test_notification_handles_multiple_items(): void
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $customer = User::factory()->create();

        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'vendor_id' => $vendor->id,
        ]);

        $product1 = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Chocolate Box',
        ]);

        $product2 = Product::factory()->create([
            'vendor_id' => $vendor->id,
            'name' => 'Flower Bouquet',
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product1->id,
        ]);

        OrderItem::factory()->create([
            'order_id' => $order->id,
            'orderable_type' => Product::class,
            'orderable_id' => $product2->id,
        ]);

        $order->load(['items.orderable', 'user']);

        $notification = new NewOrderReceived($order);
        $data = $notification->toDatabase($vendor);

        $this->assertStringContainsString('+ 1 more', $data['message']);
    }

    public function test_vendor_receives_notification_when_dispatched(): void
    {
        Notification::fake();

        $vendor = User::factory()->create(['role' => 'vendor']);
        $order = Order::factory()->create(['vendor_id' => $vendor->id]);
        $order->load(['items.orderable', 'user']);

        $vendor->notify(new NewOrderReceived($order));

        Notification::assertSentTo($vendor, NewOrderReceived::class, function (NewOrderReceived $notification) use ($order) {
            return $notification->order->id === $order->id;
        });
    }
}
