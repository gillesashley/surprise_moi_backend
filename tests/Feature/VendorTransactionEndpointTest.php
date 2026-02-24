<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\VendorTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorTransactionEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vendor = User::factory()->vendor()->create();
    }

    public function test_vendor_can_list_transactions(): void
    {
        $order = Order::factory()->create(['vendor_id' => $this->vendor->id]);

        VendorTransaction::factory()->count(3)->create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/transactions?page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'transactions',
                    'pagination' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonPath('data.pagination.total', 3);
    }

    public function test_non_vendor_cannot_access_transactions(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->getJson('/api/v1/vendor/transactions');

        $response->assertStatus(403);
    }

    public function test_transactions_endpoint_handles_deleted_orders(): void
    {
        // Create a transaction whose order has been deleted (orphaned order_id)
        $order = Order::factory()->create(['vendor_id' => $this->vendor->id]);
        $orderId = $order->id;

        VendorTransaction::factory()->create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $orderId,
        ]);

        // Delete the order to simulate orphaned reference
        $order->forceDelete();

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/transactions?page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 1);
    }

    public function test_transactions_with_zero_results(): void
    {
        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/transactions?page=1&per_page=20');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 0)
            ->assertJsonPath('data.transactions', []);
    }

    public function test_transactions_pagination_works(): void
    {
        $order = Order::factory()->create(['vendor_id' => $this->vendor->id]);

        VendorTransaction::factory()->count(5)->create([
            'vendor_id' => $this->vendor->id,
            'order_id' => $order->id,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/transactions?page=1&per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total', 5)
            ->assertJsonCount(2, 'data.transactions');
    }

    public function test_unauthenticated_user_cannot_access_transactions(): void
    {
        $response = $this->getJson('/api/v1/vendor/transactions');

        $response->assertStatus(401);
    }
}
