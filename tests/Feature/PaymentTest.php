<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->order = Order::factory()->create([
            'user_id' => $this->user->id,
            'total' => 100.00,
            'payment_status' => 'unpaid',
            'status' => 'pending',
        ]);
    }

    // ==========================================
    // Payment Initiation Tests
    // ==========================================

    public function test_user_can_initiate_payment_for_their_order(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test-url',
                    'access_code' => 'test_access_code',
                    'reference' => 'test_reference',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'authorization_url',
                    'access_code',
                    'reference',
                ],
                'payment' => [
                    'id',
                    'reference',
                    'amount',
                    'status',
                ],
            ]);

        $this->assertDatabaseHas('payments', [
            'order_id' => $this->order->id,
            'user_id' => $this->user->id,
            'status' => Payment::STATUS_PENDING,
        ]);

        // Order payment status should be updated
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_user_cannot_initiate_payment_for_another_users_order(): void
    {
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You are not authorized to pay for this order.',
            ]);
    }

    public function test_cannot_initiate_payment_for_already_paid_order(): void
    {
        $this->order->update(['payment_status' => 'paid']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This order has already been paid.',
            ]);
    }

    public function test_cannot_initiate_payment_for_zero_total_order(): void
    {
        $this->order->update(['total' => 0]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Order total must be greater than zero.',
            ]);
    }

    public function test_returns_existing_pending_payment_on_reinitiate(): void
    {
        // Create an existing pending payment
        $existingPayment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'authorization_url' => 'https://checkout.paystack.com/existing-url',
            'access_code' => 'existing_access_code',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $this->order->id,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/existing-url',
                    'access_code' => 'existing_access_code',
                    'reference' => $existingPayment->reference,
                ],
            ]);

        // No new payment should be created
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_payment_initiation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/payments/initiate', [
            'order_id' => $this->order->id,
        ]);

        $response->assertStatus(401);
    }

    // ==========================================
    // Payment Verification Tests
    // ==========================================

    public function test_user_can_verify_successful_payment(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => 10000,
                    'currency' => 'GHS',
                    'channel' => 'card',
                    'gateway_response' => 'Successful',
                    'authorization' => [
                        'last4' => '4081',
                        'card_type' => 'visa',
                        'exp_month' => '12',
                        'exp_year' => '2025',
                        'bank' => 'Test Bank',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Payment verified successfully.',
                'data' => [
                    'status' => 'success',
                ],
            ]);

        // Payment status should be updated
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
        ]);

        // Order should be marked as paid and confirmed
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
            'status' => 'confirmed',
        ]);
    }

    public function test_verify_failed_payment(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'failed',
                    'reference' => $payment->reference,
                    'gateway_response' => 'Insufficient funds',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'failed',
        ]);
    }

    public function test_pending_verification_status_does_not_mark_payment_as_failed(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'status' => Payment::STATUS_PROCESSING,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
            'verified_at' => null,
            'failure_reason' => null,
        ]);

        $this->order->update(['payment_status' => 'pending']);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'pending',
                    'reference' => $payment->reference,
                    'amount' => 10000,
                    'gateway_response' => 'Awaiting customer authorization',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Payment is still processing. Please wait.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_PROCESSING,
            'failure_reason' => null,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'pending',
        ]);
    }

    public function test_user_cannot_verify_another_users_payment(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $response->assertStatus(403);
    }

    public function test_verify_nonexistent_payment_reference(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => 'INVALID_REFERENCE',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reference']);
    }

    public function test_already_verified_payment_returns_cached_result(): void
    {
        $payment = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        // No HTTP request should be made since payment is already verified
        Http::fake();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Payment already verified.',
            ]);

        Http::assertNothingSent();
    }

    // ==========================================
    // Webhook Tests
    // ==========================================

    public function test_webhook_processes_successful_charge(): void
    {
        $payment = Payment::factory()->pending()->create([
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        $webhookPayload = [
            'event' => 'charge.success',
            'data' => [
                'status' => 'success',
                'reference' => $payment->reference,
                'amount' => 10000,
                'channel' => 'card',
                'gateway_response' => 'Successful',
                'authorization' => [
                    'last4' => '4081',
                    'card_type' => 'visa',
                ],
            ],
        ];

        // Use the same JSON string for both signature and request body
        $jsonPayload = json_encode($webhookPayload);
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.webhook_secret'));

        $response = $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
        ]);
    }

    public function test_webhook_processes_failed_charge(): void
    {
        $payment = Payment::factory()->pending()->create();

        $webhookPayload = [
            'event' => 'charge.failed',
            'data' => [
                'reference' => $payment->reference,
                'gateway_response' => 'Card declined',
            ],
        ];

        // Use the same JSON string for both signature and request body
        $jsonPayload = json_encode($webhookPayload);
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.webhook_secret'));

        $response = $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    public function test_delayed_failed_webhook_does_not_overwrite_successful_payment(): void
    {
        // Simulate the race condition:
        // 1. Payment #1 fails but webhook is delayed
        // 2. Payment #2 succeeds -> order.payment_status = 'paid'
        // 3. Delayed webhook for Payment #1 arrives -> should NOT overwrite to 'failed'

        $payment1 = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        // Payment #2 succeeds (simulated via factory)
        $payment2 = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        // Order should be marked as paid
        $this->order->update(['payment_status' => 'paid']);

        // Now a delayed failure webhook arrives for payment #1
        $webhookPayload = [
            'event' => 'charge.failed',
            'data' => [
                'reference' => $payment1->reference,
                'gateway_response' => 'Card declined',
            ],
        ];

        $jsonPayload = json_encode($webhookPayload);
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.webhook_secret'));

        $response = $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        $response->assertStatus(200);

        // Payment #1 should be marked as failed
        $this->assertDatabaseHas('payments', [
            'id' => $payment1->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        // BUT order should still be 'paid' (not overwritten to 'failed')
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $webhookPayload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'test_ref',
            ],
        ];

        $jsonPayload = json_encode($webhookPayload);

        $response = $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => 'invalid_signature',
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        $response->assertStatus(401);
    }

    public function test_webhook_ignores_unknown_events(): void
    {
        $webhookPayload = [
            'event' => 'unknown.event',
            'data' => [],
        ];

        // Use the same JSON string for both signature and request body
        $jsonPayload = json_encode($webhookPayload);
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.webhook_secret'));

        $response = $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Event ignored.',
            ]);
    }

    // ==========================================
    // Payment History Tests
    // ==========================================

    public function test_user_can_view_their_payment_history(): void
    {
        Payment::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        // Create payments for another user
        Payment::factory()->count(2)->create();

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/payments');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_user_can_view_single_payment(): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'payment' => [
                    'id',
                    'reference',
                    'amount',
                    'status',
                    'order',
                ],
            ]);
    }

    public function test_user_cannot_view_another_users_payment(): void
    {
        $payment = Payment::factory()->create();

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/{$payment->id}");

        $response->assertStatus(403);
    }

    // ==========================================
    // Payment Retry Tests
    // ==========================================

    public function test_user_can_retry_failed_payment(): void
    {
        $payment = Payment::factory()->failed()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'message' => 'Authorization URL created',
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/new-url',
                    'access_code' => 'new_access_code',
                    'reference' => 'new_reference',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/retry");

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'New payment initialized successfully.',
            ]);

        // Old payment should be cancelled
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_CANCELLED,
        ]);

        // New payment should be created
        $this->assertDatabaseCount('payments', 2);
    }

    public function test_cannot_retry_successful_payment(): void
    {
        $payment = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/retry");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Only failed or abandoned payments can be retried.',
            ]);
    }

    public function test_cannot_retry_pending_payment(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$payment->id}/retry");

        $response->assertStatus(422);
    }

    // ==========================================
    // Order Payment Status Tests
    // ==========================================

    public function test_user_can_get_order_payment_status(): void
    {
        $payment = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/order/{$this->order->id}");

        $response->assertStatus(200)
            ->assertJson([
                'payment_status' => Payment::STATUS_SUCCESS,
            ]);
    }

    public function test_returns_404_for_order_without_payment(): void
    {
        $newOrder = Order::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/payments/order/{$newOrder->id}");

        $response->assertStatus(404)
            ->assertJson([
                'payment_status' => 'unpaid',
            ]);
    }

    // ==========================================
    // Callback Tests
    // ==========================================

    public function test_callback_verifies_payment_and_returns_status(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => 10000,
                ],
            ], 200),
        ]);

        $response = $this->get("/api/v1/payments/callback?reference={$payment->reference}");

        $expectedDeepLink = "surprisemoi://payment-callback?status=success&type=order&reference={$payment->reference}&order_id={$this->order->id}";
        $response->assertOk();
        $response->assertSee($expectedDeepLink, false);
    }

    public function test_callback_requires_reference(): void
    {
        $response = $this->get('/api/v1/payments/callback');

        $response->assertOk();
        $response->assertSee('surprisemoi://payment-callback?status=failed&type=order&message=Payment+reference+is+required', false);
    }

    public function test_callback_skips_reverification_for_successful_payment(): void
    {
        $payment = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        Http::fake();

        $response = $this->get("/api/v1/payments/callback?reference={$payment->reference}");

        $expectedDeepLink = "surprisemoi://payment-callback?status=success&type=order&reference={$payment->reference}&order_id={$this->order->id}";
        $response->assertOk();
        $response->assertSee($expectedDeepLink, false);

        Http::assertNothingSent();
    }

    public function test_callback_skips_reverification_for_failed_payment(): void
    {
        $payment = Payment::factory()->failed()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'failure_reason' => 'Insufficient funds',
        ]);

        Http::fake();

        $response = $this->get("/api/v1/payments/callback?reference={$payment->reference}");

        $expectedDeepLink = "surprisemoi://payment-callback?status=failed&type=order&reference={$payment->reference}&order_id={$this->order->id}&message=Insufficient+funds";
        $response->assertOk();
        $response->assertSee($expectedDeepLink, false);

        Http::assertNothingSent();
    }

    public function test_callback_handles_post_requests_from_3ds(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => 10000,
                ],
            ], 200),
        ]);

        // Simulate POST callback (some 3DS flows POST instead of GET)
        $response = $this->post('/api/v1/payments/callback', [
            'reference' => $payment->reference,
        ]);

        $expectedDeepLink = "surprisemoi://payment-callback?status=success&type=order&reference={$payment->reference}&order_id={$this->order->id}";
        $response->assertOk();
        $response->assertSee($expectedDeepLink, false);
    }

    // ==========================================
    // Rate Limiting Tests
    // ==========================================

    public function test_payment_initiation_is_rate_limited(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/test',
                    'access_code' => 'test',
                    'reference' => 'test',
                ],
            ], 200),
        ]);

        // Make 5 successful requests
        for ($i = 0; $i < 5; $i++) {
            $order = Order::factory()->create([
                'user_id' => $this->user->id,
                'payment_status' => 'unpaid',
            ]);

            $this->actingAs($this->user)
                ->postJson('/api/v1/payments/initiate', [
                    'order_id' => $order->id,
                ]);
        }

        // 6th request should be rate limited
        $anotherOrder = Order::factory()->create([
            'user_id' => $this->user->id,
            'payment_status' => 'unpaid',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/initiate', [
                'order_id' => $anotherOrder->id,
            ]);

        $response->assertStatus(429);
    }

    // ==========================================
    // Amount Mismatch Test
    // ==========================================

    public function test_payment_fails_on_amount_mismatch(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'message' => 'Verification successful',
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => 5000, // Only half the expected amount
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Payment amount does not match order total.',
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_FAILED,
        ]);
    }

    // ==========================================
    // Card and Mobile Money Details Tests
    // ==========================================

    public function test_card_details_are_stored_on_successful_payment(): void
    {
        $payment = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'reference' => $payment->reference,
                    'amount' => 10000,
                    'channel' => 'card',
                    'authorization' => [
                        'last4' => '4081',
                        'card_type' => 'visa',
                        'exp_month' => '12',
                        'exp_year' => '2025',
                        'bank' => 'Test Bank',
                    ],
                ],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $payment->reference,
            ]);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'channel' => 'card',
            'card_last4' => '4081',
            'card_type' => 'visa',
            'card_exp_month' => '12',
            'card_exp_year' => '2025',
        ]);
    }

    // ==========================================
    // Payment Status Sync / Race Condition Tests
    // ==========================================

    public function test_failed_webhook_does_not_overwrite_successful_payment_status(): void
    {
        // Simulate: Payment A failed, Payment B succeeded for the same order
        $paymentA = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $paymentB = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        // Order is marked as paid from Payment B
        $this->order->update(['payment_status' => 'paid']);

        // Now a late charge.failed webhook arrives for Payment A
        $webhookPayload = [
            'event' => 'charge.failed',
            'data' => [
                'reference' => $paymentA->reference,
                'gateway_response' => 'Card declined',
            ],
        ];

        $jsonPayload = json_encode($webhookPayload);
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.webhook_secret'));

        $response = $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        $response->assertStatus(200);

        // Payment A should be failed
        $this->assertDatabaseHas('payments', [
            'id' => $paymentA->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        // But the ORDER should still be paid (not overwritten to failed)
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_failed_webhook_does_not_overwrite_already_successful_payment(): void
    {
        // Payment was already marked as successful
        $payment = Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $this->order->update(['payment_status' => 'paid']);

        // A late charge.failed webhook arrives for the same payment
        $webhookPayload = [
            'event' => 'charge.failed',
            'data' => [
                'reference' => $payment->reference,
                'gateway_response' => 'Timeout',
            ],
        ];

        $jsonPayload = json_encode($webhookPayload);
        $signature = hash_hmac('sha512', $jsonPayload, config('services.paystack.webhook_secret'));

        $this->call('POST', '/api/v1/payments/webhook', [], [], [], [
            'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $jsonPayload);

        // Payment should remain successful
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => Payment::STATUS_SUCCESS,
        ]);

        // Order should remain paid
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
        ]);
    }

    public function test_verify_failed_does_not_overwrite_order_with_successful_payment(): void
    {
        // Payment A already succeeded for this order
        Payment::factory()->successful()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
        ]);

        $this->order->update(['payment_status' => 'paid']);

        // Payment B is pending and gets verified as failed
        $paymentB = Payment::factory()->pending()->create([
            'user_id' => $this->user->id,
            'order_id' => $this->order->id,
            'amount' => 100.00,
            'amount_in_kobo' => 10000,
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'failed',
                    'reference' => $paymentB->reference,
                    'amount' => 10000,
                    'gateway_response' => 'Declined',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/payments/verify', [
                'reference' => $paymentB->reference,
            ]);

        // Payment B should be failed
        $this->assertDatabaseHas('payments', [
            'id' => $paymentB->id,
            'status' => Payment::STATUS_FAILED,
        ]);

        // But order should still be paid
        $this->assertDatabaseHas('orders', [
            'id' => $this->order->id,
            'payment_status' => 'paid',
        ]);
    }
}
