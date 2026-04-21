<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorFieldVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendor_can_see_their_own_verification_status(): void
    {
        $vendor = User::factory()->vendor()->create([
            'field_verified_at' => now()->subDay(),
            'field_verified_until' => now()->addMonths(11),
        ]);
        $visit = VendorVisit::factory()->passed()->create([
            'vendor_user_id' => $vendor->id,
            'started_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($vendor, 'sanctum')
            ->getJson('/api/v1/vendor/field-verification');

        if ($response->status() === 500) {
            dump($response->json());
        }

        $response->assertOk()
            ->assertJsonPath('data.is_field_verified', true)
            ->assertJsonCount(1, 'data.visits')
            ->assertJsonPath('data.visits.0.id', $visit->id);
    }

    public function test_vendor_cannot_see_others_verification_status(): void
    {
        $vendor1 = User::factory()->vendor()->create();
        $vendor2 = User::factory()->vendor()->create();

        $response = $this->actingAs($vendor1, 'sanctum')
            ->getJson('/api/v1/vendor/field-verification');

        // Sanity check: it should only show vendor1's data (which is empty/default)
        $response->assertOk()
            ->assertJsonPath('data.is_field_verified', false)
            ->assertJsonCount(0, 'data.visits');
    }

    public function test_customer_cannot_access_vendor_verification_api(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/vendor/field-verification');

        $response->assertForbidden();
    }
}
