<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\VendorPayoutDetail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VendorPayoutDetailTest extends TestCase
{
    use RefreshDatabase;

    protected User $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = User::factory()->vendor()->create();
    }

    public function test_vendor_can_save_mobile_money_payout_details(): void
    {
        Http::fake([
            'https://api.paystack.co/bank/resolve*' => Http::response([
                'status' => true,
                'data' => ['account_number' => '0241234567', 'account_name' => 'John Doe'],
            ], 200),
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'data' => [
                    'recipient_code' => 'RCP_test123',
                    'details' => ['bank_name' => 'MTN Mobile Money'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0241234567',
                'bank_code' => 'MTN',
                'provider' => 'mtn',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('vendor_payout_details', [
            'vendor_id' => $this->vendor->id,
            'payout_method' => 'mobile_money',
            'paystack_recipient_code' => 'RCP_test123',
            'is_verified' => true,
        ]);
    }

    public function test_vendor_can_save_bank_transfer_payout_details(): void
    {
        Http::fake([
            'https://api.paystack.co/bank/resolve*' => Http::response([
                'status' => true,
                'data' => ['account_number' => '1234567890', 'account_name' => 'Jane Doe'],
            ], 200),
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'data' => [
                    'recipient_code' => 'RCP_bank456',
                    'details' => ['bank_name' => 'GCB Bank'],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'bank_transfer',
                'account_number' => '1234567890',
                'bank_code' => 'GH010',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('vendor_payout_details', [
            'vendor_id' => $this->vendor->id,
            'payout_method' => 'bank_transfer',
        ]);
    }

    public function test_store_fails_when_paystack_cannot_resolve_account(): void
    {
        Http::fake([
            'https://api.paystack.co/bank/resolve*' => Http::response([
                'status' => false,
                'message' => 'Could not resolve account name',
            ], 422),
        ]);

        $response = $this->actingAs($this->vendor)
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0000000000',
                'bank_code' => 'MTN',
                'provider' => 'mtn',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_vendor_can_list_payout_details(): void
    {
        VendorPayoutDetail::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor)
            ->getJson('/api/v1/vendor/payout-details');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'payout_details');
    }

    public function test_vendor_can_delete_payout_details(): void
    {
        Http::fake([
            'https://api.paystack.co/transferrecipient/*' => Http::response(['status' => true], 200),
        ]);

        $detail = VendorPayoutDetail::factory()->create(['vendor_id' => $this->vendor->id]);

        $response = $this->actingAs($this->vendor)
            ->deleteJson("/api/v1/vendor/payout-details/{$detail->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('vendor_payout_details', ['id' => $detail->id]);
    }

    public function test_vendor_cannot_delete_other_vendors_payout_details(): void
    {
        $otherVendor = User::factory()->vendor()->create();
        $detail = VendorPayoutDetail::factory()->create(['vendor_id' => $otherVendor->id]);

        $response = $this->actingAs($this->vendor)
            ->deleteJson("/api/v1/vendor/payout-details/{$detail->id}");

        $response->assertStatus(403);
    }

    public function test_non_vendor_cannot_save_payout_details(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer)
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0241234567',
                'bank_code' => 'MTN',
                'provider' => 'mtn',
            ]);

        $response->assertStatus(403);
    }
}
