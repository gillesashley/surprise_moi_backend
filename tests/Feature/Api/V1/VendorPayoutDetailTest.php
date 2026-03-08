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
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'data' => [
                    'recipient_code' => 'RCP_momo_test123',
                    'details' => ['bank_name' => 'MTN Mobile Money'],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0244123456',
                'bank_code' => 'MTN',
                'account_name' => 'Kwame Asante',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('payout_detail.payout_method', 'mobile_money')
            ->assertJsonPath('payout_detail.account_number', '0244123456')
            ->assertJsonPath('payout_detail.account_name', 'Kwame Asante')
            ->assertJsonPath('payout_detail.is_verified', true)
            ->assertJsonPath('payout_detail.is_default', true);

        $this->assertDatabaseHas('vendor_payout_details', [
            'vendor_id' => $this->vendor->id,
            'payout_method' => 'mobile_money',
            'account_number' => '0244123456',
            'bank_code' => 'MTN',
            'account_name' => 'Kwame Asante',
            'is_verified' => true,
            'is_default' => true,
            'paystack_recipient_code' => 'RCP_momo_test123',
        ]);
    }

    public function test_mobile_money_save_fails_when_paystack_rejects(): void
    {
        Http::fake([
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => false,
                'message' => 'Could not verify mobile money account.',
            ], 400),
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0000000000',
                'bank_code' => 'MTN',
                'account_name' => 'Invalid User',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('vendor_payout_details', [
            'vendor_id' => $this->vendor->id,
            'account_number' => '0000000000',
        ]);
    }

    public function test_vendor_can_save_bank_transfer_payout_details(): void
    {
        Http::fake([
            'https://api.paystack.co/bank/resolve*' => Http::response([
                'status' => true,
                'data' => ['account_name' => 'Kwame Asante'],
            ]),
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'data' => [
                    'recipient_code' => 'RCP_test123',
                    'details' => ['bank_name' => 'GCB Bank'],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'bank_transfer',
                'account_number' => '1234567890',
                'bank_code' => 'GH050',
                'account_name' => 'Kwame Asante',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('payout_detail.payout_method', 'bank_transfer')
            ->assertJsonPath('payout_detail.is_verified', true);
    }

    public function test_mobile_money_does_not_require_provider_field(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0244123456',
                'bank_code' => 'MTN',
                'account_name' => 'Kwame Asante',
            ]);

        $response->assertStatus(201);
    }

    public function test_new_payout_detail_replaces_existing_default(): void
    {
        VendorPayoutDetail::factory()->create([
            'vendor_id' => $this->vendor->id,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0551234567',
                'bank_code' => 'VOD',
                'account_name' => 'Ama Mensah',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseCount('vendor_payout_details', 2);
        $this->assertEquals(1, $this->vendor->payoutDetails()->where('is_default', true)->count());
    }

    public function test_non_vendor_cannot_save_payout_details(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'mobile_money',
                'account_number' => '0244123456',
                'bank_code' => 'MTN',
                'account_name' => 'Kwame Asante',
            ]);

        $response->assertStatus(403);
    }

    public function test_vendor_can_list_saved_payout_details(): void
    {
        VendorPayoutDetail::factory()->count(2)->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/payout-details');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'payout_details');
    }

    public function test_vendor_can_list_mobile_money_providers(): void
    {
        Http::fake([
            'https://api.paystack.co/bank?currency=GHS&type=mobile_money' => Http::response([
                'status' => true,
                'data' => [
                    ['name' => 'MTN', 'code' => 'MTN', 'type' => 'mobile_money'],
                    ['name' => 'Vodafone', 'code' => 'VOD', 'type' => 'mobile_money'],
                    ['name' => 'AirtelTigo', 'code' => 'ATL', 'type' => 'mobile_money'],
                ],
            ]),
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/payout-details/mobile-money-providers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'providers');
    }

    public function test_validation_rejects_invalid_payout_method(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/payout-details', [
                'payout_method' => 'bitcoin',
                'account_number' => '0244123456',
                'bank_code' => 'MTN',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('payout_method');
    }
}
