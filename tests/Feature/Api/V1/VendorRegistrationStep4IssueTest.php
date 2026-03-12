<?php

namespace Tests\Feature\Api\V1;

use App\Models\BespokeService;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Comprehensive test for Step 4 registration issue
 * Testing the exact scenario from the mobile app
 */
class VendorRegistrationStep4IssueTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test services
        BespokeService::factory()->create(['id' => 1, 'name' => 'Proposal Setup', 'is_active' => true]);
        BespokeService::factory()->create(['id' => 2, 'name' => 'Same-Day Delivery', 'is_active' => true]);
        BespokeService::factory()->create(['id' => 3, 'name' => 'Midnight Delivery', 'is_active' => true]);

        $this->user = User::factory()->create();
    }

    /**
     * Test the exact flow: Complete steps 1-3, then step 4
     */
    public function test_complete_vendor_registration_flow_step_4_saves_correctly(): void
    {
        // Create an application that has completed step 3
        $application = VendorApplication::factory()
            ->for($this->user)
            ->create([
                'completed_step' => 3,
                'current_step' => 4,
                'ghana_card_front' => 'test-front.jpg',
                'ghana_card_back' => 'test-back.jpg',
                'has_business_certificate' => false,
                'has_tin' => false,
                'selfie_image' => 'test-selfie.jpg',
                'mobile_money_number' => '0240000000',
                'mobile_money_provider' => 'mtn',
                'proof_of_business' => 'test-proof.jpg',
                'payment_required' => false,
            ]);

        // Verify starting state
        $this->assertEquals(3, $application->completed_step);
        $this->assertEquals(0, $application->bespokeServices()->count());

        // Call step 4 endpoint
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [1, 2],
            ]);

        // Assert response is successful
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Bespoke services selected. Please review and submit your application.',
            ])
            ->assertJsonPath('data.next_step', 5)
            ->assertJsonPath('data.can_submit', true);

        // Refresh the application from database
        $application->refresh();

        // THE CRITICAL CHECKS
        $this->assertEquals(4, $application->completed_step, 'completed_step should be 4');
        $this->assertEquals(5, $application->current_step, 'current_step should be 5');
        $this->assertEquals(2, $application->bespokeServices()->count(), 'Should have 2 services attached');

        // Verify the pivot table has the entries
        $this->assertDatabaseHas('vendor_application_services', [
            'vendor_application_id' => $application->id,
            'bespoke_service_id' => 1,
        ]);

        $this->assertDatabaseHas('vendor_application_services', [
            'vendor_application_id' => $application->id,
            'bespoke_service_id' => 2,
        ]);
    }

    /**
     * Test that updating services on step 4 multiple times works
     */
    public function test_step_4_can_be_updated_multiple_times_before_submission(): void
    {
        $application = VendorApplication::factory()
            ->for($this->user)
            ->create([
                'completed_step' => 3,
                'current_step' => 4,
                'ghana_card_front' => 'test-front.jpg',
                'ghana_card_back' => 'test-back.jpg',
                'has_business_certificate' => false,
                'has_tin' => false,
                'selfie_image' => 'test-selfie.jpg',
                'mobile_money_number' => '0240000000',
                'mobile_money_provider' => 'mtn',
                'proof_of_business' => 'test-proof.jpg',
            ]);

        // First submission
        $response1 = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [1],
            ]);

        $response1->assertStatus(200);
        $application->refresh();
        $this->assertEquals(1, $application->bespokeServices()->count());
        $this->assertEquals(4, $application->completed_step);

        // Second submission (user changes their mind)
        $response2 = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [2, 3],
            ]);

        $response2->assertStatus(200);
        $application->refresh();

        // Services should be synced (replaced, not added)
        $this->assertEquals(2, $application->bespokeServices()->count());
        $this->assertEquals(4, $application->completed_step);

        // Verify correct services
        $serviceIds = $application->bespokeServices()->pluck('id')->toArray();
        $this->assertContains(2, $serviceIds);
        $this->assertContains(3, $serviceIds);
        $this->assertNotContains(1, $serviceIds);
    }

    /**
     * Test step 4 with registered vendor (business documents path)
     */
    public function test_step_4_works_for_registered_vendors(): void
    {
        $application = VendorApplication::factory()
            ->for($this->user)
            ->create([
                'completed_step' => 3,
                'current_step' => 4,
                'ghana_card_front' => 'test-front.jpg',
                'ghana_card_back' => 'test-back.jpg',
                'has_business_certificate' => true,
                'has_tin' => true,
                'business_certificate_document' => 'cert.pdf',
                'tin_document' => 'tin.pdf',
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [1, 2, 3],
            ]);

        $response->assertStatus(200);

        $application->refresh();
        $this->assertEquals(4, $application->completed_step);
        $this->assertEquals(3, $application->bespokeServices()->count());
    }

    /**
     * Test that empty service_ids array is rejected
     */
    public function test_step_4_requires_at_least_one_service(): void
    {
        $application = VendorApplication::factory()
            ->for($this->user)
            ->create([
                'completed_step' => 3,
                'ghana_card_front' => 'test-front.jpg',
                'ghana_card_back' => 'test-back.jpg',
                'has_business_certificate' => false,
                'has_tin' => false,
                'selfie_image' => 'test-selfie.jpg',
                'mobile_money_number' => '0240000000',
                'mobile_money_provider' => 'mtn',
                'proof_of_business' => 'test-proof.jpg',
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_ids']);

        $application->refresh();
        $this->assertEquals(3, $application->completed_step, 'completed_step should remain 3 on validation failure');
    }

    /**
     * Test that invalid service IDs are rejected
     */
    public function test_step_4_rejects_invalid_service_ids(): void
    {
        $application = VendorApplication::factory()
            ->for($this->user)
            ->create([
                'completed_step' => 3,
                'ghana_card_front' => 'test-front.jpg',
                'ghana_card_back' => 'test-back.jpg',
                'has_business_certificate' => false,
                'has_tin' => false,
                'selfie_image' => 'test-selfie.jpg',
                'mobile_money_number' => '0240000000',
                'mobile_money_provider' => 'mtn',
                'proof_of_business' => 'test-proof.jpg',
            ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [999, 1000], // Non-existent IDs
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_ids.0', 'service_ids.1']);

        $application->refresh();
        $this->assertEquals(3, $application->completed_step);
        $this->assertEquals(0, $application->bespokeServices()->count());
    }

    /**
     * Test database transaction rollback on error
     */
    public function test_step_4_rolls_back_on_database_error(): void
    {
        $application = VendorApplication::factory()
            ->for($this->user)
            ->create([
                'completed_step' => 3,
                'ghana_card_front' => 'test-front.jpg',
                'ghana_card_back' => 'test-back.jpg',
                'has_business_certificate' => false,
                'has_tin' => false,
                'selfie_image' => 'test-selfie.jpg',
                'mobile_money_number' => '0240000000',
                'mobile_money_provider' => 'mtn',
                'proof_of_business' => 'test-proof.jpg',
            ]);

        // Test with a mix of valid and invalid IDs
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
                'service_ids' => [1, 'invalid'],
            ]);

        $response->assertStatus(422);

        $application->refresh();

        // Nothing should have been saved
        $this->assertEquals(3, $application->completed_step);
        $this->assertEquals(0, $application->bespokeServices()->count());
    }
}
