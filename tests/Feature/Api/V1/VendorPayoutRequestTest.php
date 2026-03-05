<?php

namespace Tests\Feature\Api\V1;

use App\Models\PayoutRequest;
use App\Models\User;
use App\Models\VendorBalance;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorPayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected VendorBalance $balance;

    protected VendorPayoutDetail $payoutDetail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = User::factory()->vendor()->create();
        $this->balance = VendorBalance::factory()->create([
            'vendor_id' => $this->vendor->id,
            'available_balance' => 5000.00,
            'pending_balance' => 1000.00,
            'total_earned' => 10000.00,
            'total_withdrawn' => 4000.00,
        ]);
        $this->payoutDetail = VendorPayoutDetail::factory()->create([
            'vendor_id' => $this->vendor->id,
        ]);
    }

    public function test_vendor_can_request_payout_with_saved_details(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 500,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('payout_requests', [
            'user_id' => $this->vendor->id,
            'amount' => 500.00,
            'payout_detail_id' => $this->payoutDetail->id,
            'status' => 'pending',
        ]);

        $this->balance->refresh();
        $this->assertEquals(4500.00, (float) $this->balance->available_balance);
    }

    public function test_payout_request_fails_below_minimum(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 10,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_payout_request_fails_above_maximum(): void
    {
        $this->balance->update(['available_balance' => 20000.00]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 15000,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_payout_request_fails_with_insufficient_balance(): void
    {
        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 6000,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(400);
    }

    public function test_payout_request_fails_with_existing_pending_request(): void
    {
        PayoutRequest::factory()->create([
            'user_id' => $this->vendor->id,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 100,
                'payout_detail_id' => $this->payoutDetail->id,
            ]);

        $response->assertStatus(400);
    }

    public function test_payout_request_fails_with_other_vendors_payout_detail(): void
    {
        $otherDetail = VendorPayoutDetail::factory()->create();

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payouts/request', [
                'amount' => 100,
                'payout_detail_id' => $otherDetail->id,
            ]);

        $response->assertStatus(422);
    }

    public function test_vendor_can_list_payout_history(): void
    {
        PayoutRequest::factory()->count(3)->create([
            'user_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/payouts');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
