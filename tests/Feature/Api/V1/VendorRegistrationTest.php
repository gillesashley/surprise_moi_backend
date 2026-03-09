<?php

namespace Tests\Feature\Api\V1;

use App\Models\BespokeService;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VendorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        // Seed bespoke services for testing
        $this->seedBespokeServices();
    }

    private function seedBespokeServices(): void
    {
        BespokeService::factory()->create([
            'name' => 'Proposal Setup',
            'slug' => 'proposal-setup',
            'sort_order' => 1,
        ]);
        BespokeService::factory()->create([
            'name' => 'Same-Day Delivery',
            'slug' => 'same-day-delivery',
            'sort_order' => 2,
        ]);
        BespokeService::factory()->create([
            'name' => 'Midnight Delivery',
            'slug' => 'midnight-delivery',
            'sort_order' => 3,
        ]);
    }

    /**
     * Create a fake image file without requiring GD extension.
     */
    private function createFakeImage(string $name = 'image.jpg'): UploadedFile
    {
        // Minimal valid JPEG file header
        $jpegContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'
            .'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/2wBDAQkJCQwLDBgNDRgyIRwhMjIyMjIyMjIyMjIyMjIyMjIy'
            .'MjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjIyMjL/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAn/'
            .'xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMB'
            .'AAIRAxEAPwCwAB//2Q==');

        return UploadedFile::fake()->createWithContent($name, $jpegContent);
    }

    // ==========================================
    // Status Endpoint Tests
    // ==========================================

    public function test_user_can_get_application_status_without_application(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/vendor-registration/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_application' => false,
                ],
            ]);
    }

    public function test_user_can_get_application_status_with_application(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->create();

        $response = $this->actingAs($user)->getJson('/api/v1/vendor-registration/status');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'has_application' => true,
                ],
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'has_application',
                    'application' => [
                        'id',
                        'status',
                        'current_step',
                        'completed_step',
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_user_cannot_access_status(): void
    {
        $response = $this->getJson('/api/v1/vendor-registration/status');

        $response->assertStatus(401);
    }

    // ==========================================
    // Bespoke Services Endpoint Tests
    // ==========================================

    public function test_user_can_get_available_bespoke_services(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/v1/vendor-registration/bespoke-services');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonCount(3, 'data');
    }

    // ==========================================
    // Step 1: Ghana Card Upload Tests
    // ==========================================

    public function test_user_can_upload_ghana_card(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-1/ghana-card', [
            'ghana_card_front' => $this->createFakeImage('front.jpg'),
            'ghana_card_back' => $this->createFakeImage('back.jpg'),
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'next_step' => 2,
                ],
            ]);

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'completed_step' => 1,
            'current_step' => 2,
        ]);

        Storage::disk()->assertExists(
            VendorApplication::where('user_id', $user->id)->first()->ghana_card_front
        );
    }

    public function test_ghana_card_upload_requires_both_images(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-1/ghana-card', [
            'ghana_card_front' => $this->createFakeImage('front.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ghana_card_back']);
    }

    public function test_ghana_card_upload_validates_file_type(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-1/ghana-card', [
            'ghana_card_front' => UploadedFile::fake()->create('front.pdf', 100),
            'ghana_card_back' => $this->createFakeImage('back.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['ghana_card_front']);
    }

    public function test_user_cannot_upload_ghana_card_with_submitted_pending_application(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->pending()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-1/ghana-card', [
            'ghana_card_front' => $this->createFakeImage('front.jpg'),
            'ghana_card_back' => $this->createFakeImage('back.jpg'),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_user_can_reupload_ghana_card_for_editable_application(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->create([
                'status' => VendorApplication::STATUS_PENDING,
                'submitted_at' => null,
                'completed_step' => 2,
                'current_step' => 3,
            ]);

        $oldFront = $application->ghana_card_front;

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-1/ghana-card', [
            'ghana_card_front' => $this->createFakeImage('new_front.jpg'),
            'ghana_card_back' => $this->createFakeImage('new_back.jpg'),
        ]);

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $application->refresh();
        $this->assertNotEquals($oldFront, $application->ghana_card_front);
        $this->assertEquals(2, $application->completed_step);
    }

    // ==========================================
    // Step 2: Business Registration Tests
    // ==========================================

    public function test_user_can_save_business_registration_as_registered_vendor(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-2/business-registration', [
            'has_business_certificate' => true,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'next_step' => 3,
                    'vendor_type' => 'registered',
                ],
            ]);

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'has_business_certificate' => true,
            'completed_step' => 2,
        ]);
    }

    public function test_user_can_save_business_registration_as_unregistered_vendor(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-2/business-registration', [
            'has_business_certificate' => false,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'vendor_type' => 'unregistered',
                ],
            ]);
    }

    public function test_step_2_requires_step_1_completion(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->create(['completed_step' => 0]);

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-2/business-registration', [
            'has_business_certificate' => true,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete Step 1 (Ghana Card upload) first.',
            ]);
    }

    // ==========================================
    // Step 3A: Registered Vendor Documents Tests
    // ==========================================

    public function test_registered_vendor_can_upload_business_documents(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->registeredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/registered-documents', [
            'business_certificate_document' => UploadedFile::fake()->create('cert.pdf', 500),
            'facebook_handle' => 'testvendor',
            'instagram_handle' => 'testvendor_ig',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'next_step' => 4,
                ],
            ]);

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'completed_step' => 3,
            'facebook_handle' => 'testvendor',
            'instagram_handle' => 'testvendor_ig',
        ]);
    }

    public function test_unregistered_vendor_cannot_use_registered_endpoint(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/registered-documents', [
            'business_certificate_document' => UploadedFile::fake()->create('cert.pdf', 500),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This endpoint is for registered vendors only. Please use the unregistered vendor endpoint.',
            ]);
    }

    // ==========================================
    // Step 3B: Unregistered Vendor Documents Tests
    // ==========================================

    public function test_unregistered_vendor_can_upload_verification_documents(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/unregistered-documents', [
            'selfie_image' => $this->createFakeImage('selfie.jpg'),
            'mobile_money_number' => '0241234567',
            'mobile_money_provider' => 'mtn',
            'proof_of_business' => UploadedFile::fake()->create('receipt.pdf', 500),
            'twitter_handle' => 'testvendor',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'next_step' => 4,
                ],
            ]);

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'mobile_money_number' => '0241234567',
            'mobile_money_provider' => 'mtn',
            'completed_step' => 3,
        ]);
    }

    public function test_unregistered_vendor_validates_mobile_money_number(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/unregistered-documents', [
            'selfie_image' => $this->createFakeImage('selfie.jpg'),
            'mobile_money_number' => '1234567890', // Invalid format
            'mobile_money_provider' => 'mtn',
            'proof_of_business' => UploadedFile::fake()->create('receipt.pdf'),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mobile_money_number']);
    }

    public function test_registered_vendor_cannot_use_unregistered_endpoint(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->registeredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/unregistered-documents', [
            'selfie_image' => $this->createFakeImage('selfie.jpg'),
            'mobile_money_number' => '0241234567',
            'mobile_money_provider' => 'mtn',
            'proof_of_business' => UploadedFile::fake()->create('receipt.pdf'),
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This endpoint is for unregistered vendors only. Please use the registered vendor endpoint.',
            ]);
    }

    public function test_mobile_money_provider_accepts_uppercase(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/unregistered-documents', [
            'selfie_image' => $this->createFakeImage('selfie.jpg'),
            'mobile_money_number' => '0241234567',
            'mobile_money_provider' => 'MTN',
            'proof_of_business' => UploadedFile::fake()->create('receipt.pdf', 500),
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'mobile_money_provider' => 'mtn',
        ]);
    }

    public function test_unregistered_vendor_documents_persists_all_fields(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-3/unregistered-documents', [
            'selfie_image' => $this->createFakeImage('selfie.jpg'),
            'mobile_money_number' => '0542441224',
            'mobile_money_provider' => 'mtn',
            'proof_of_business' => UploadedFile::fake()->create('proof.pdf', 500),
            'facebook_handle' => 'vendor_fb',
            'instagram_handle' => 'vendor_ig',
            'twitter_handle' => 'vendor_tw',
        ]);

        $response->assertStatus(200);

        $application = VendorApplication::where('user_id', $user->id)->first();

        $this->assertNotNull($application->selfie_image, 'selfie_image should be persisted');
        $this->assertEquals('0542441224', $application->mobile_money_number);
        $this->assertEquals('mtn', $application->mobile_money_provider);
        $this->assertNotNull($application->proof_of_business, 'proof_of_business should be persisted');
        $this->assertEquals('vendor_fb', $application->facebook_handle);
        $this->assertEquals('vendor_ig', $application->instagram_handle);
        $this->assertEquals('vendor_tw', $application->twitter_handle);
        $this->assertEquals(3, $application->completed_step);
        $this->assertEquals(4, $application->current_step);
    }

    public function test_changing_registration_type_resets_step3_progress(): void
    {
        $user = User::factory()->create();

        // Start as registered vendor with completed step 3A documents
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->registeredVendor()
            ->withRegisteredDocuments()
            ->create();

        $this->assertEquals(3, $application->completed_step);
        $this->assertNotNull($application->business_certificate_document);

        // Change to unregistered vendor via step 2
        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-2/business-registration', [
            'has_business_certificate' => false,
        ]);

        $response->assertStatus(200);

        $application->refresh();

        // Step 3 progress should be reset since registration type changed
        $this->assertEquals(2, $application->completed_step, 'completed_step should reset to 2 when registration type changes');
        $this->assertNull($application->business_certificate_document, 'Old step 3A documents should be cleared');
        $this->assertNull($application->selfie_image, 'Step 3B fields should be null');
        $this->assertNull($application->mobile_money_number);

        // Status should reflect step 3 as incomplete
        $statusResponse = $this->actingAs($user)->getJson('/api/v1/vendor-registration/status');
        $statusResponse->assertStatus(200);

        $appData = $statusResponse->json('data.application');
        $this->assertFalse($appData['step_3_completed'], 'step_3_completed should be false after registration type change');
        $this->assertFalse($appData['is_registered_vendor']);
    }

    public function test_same_registration_type_preserves_step3_progress(): void
    {
        $user = User::factory()->create();

        // Start as unregistered vendor with completed step 3B documents
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->create();

        $this->assertEquals(3, $application->completed_step);
        $this->assertNotNull($application->selfie_image);

        // Re-submit step 2 with the SAME registration type
        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-2/business-registration', [
            'has_business_certificate' => false,
        ]);

        $response->assertStatus(200);

        $application->refresh();

        // Step 3 progress should be preserved since registration type didn't change
        $this->assertEquals(3, $application->completed_step, 'completed_step should stay at 3');
        $this->assertNotNull($application->selfie_image, 'Step 3B documents should be preserved');
        $this->assertNotNull($application->mobile_money_number);
    }

    // ==========================================
    // Step 4: Bespoke Services Selection Tests
    // ==========================================

    public function test_user_can_select_bespoke_services(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->create();

        $serviceIds = BespokeService::pluck('id')->toArray();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
            'service_ids' => $serviceIds,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'next_step' => 5,
                    'can_submit' => true,
                ],
            ]);

        $application = VendorApplication::where('user_id', $user->id)->first();
        $this->assertEquals(count($serviceIds), $application->bespokeServices()->count());
    }

    public function test_user_must_select_at_least_one_service(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->create();

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
            'service_ids' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['service_ids']);
    }

    public function test_step_4_requires_step_3_completion(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->create(['completed_step' => 2]);

        $serviceId = BespokeService::first()->id;

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-4/bespoke-services', [
            'service_ids' => [$serviceId],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete Step 3 (Document Upload) first.',
            ]);
    }

    // ==========================================
    // Step 5: Review and Submit Tests
    // ==========================================

    public function test_user_can_review_application(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create();

        $application->bespokeServices()->attach(BespokeService::first()->id);

        $response = $this->actingAs($user)->getJson('/api/v1/vendor-registration/review');

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'application',
                    'steps_summary' => [
                        'step_1',
                        'step_2',
                        'step_3',
                        'step_4',
                    ],
                    'can_submit',
                    'is_submitted',
                ],
            ]);
    }

    public function test_user_can_submit_completed_application(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create([
                'payment_completed' => true,
                'payment_completed_at' => now(),
            ]);

        $application->bespokeServices()->attach(BespokeService::first()->id);

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/submit');

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('vendor_applications', [
            'id' => $application->id,
            'status' => VendorApplication::STATUS_PENDING,
        ]);

        $this->assertNotNull($application->fresh()->submitted_at);
    }

    public function test_unregistered_vendor_can_submit_without_payment(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create([
                'payment_required' => false,
                'payment_completed' => false,
            ]);

        $application->bespokeServices()->attach(BespokeService::first()->id);

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/submit');

        $response->assertStatus(201)
            ->assertJson(['success' => true]);

        $this->assertNotNull($application->fresh()->submitted_at);
    }

    public function test_submit_blocked_when_payment_required_but_not_completed(): void
    {
        $user = User::factory()->create();
        $application = VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->readyToSubmit()
            ->create([
                'payment_required' => true,
                'payment_completed' => false,
            ]);

        $application->bespokeServices()->attach(BespokeService::first()->id);

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/submit');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete the onboarding payment before submitting.',
            ]);
    }

    public function test_user_cannot_submit_incomplete_application(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->create(['completed_step' => 2]);

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/submit');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Please complete all registration steps first.',
            ]);
    }

    public function test_user_cannot_submit_already_submitted_application(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->pending()
            ->create(['completed_step' => 4]);

        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/submit');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
            ])
            ->assertJsonPath('message', fn ($message) => str_contains($message, 'submitted'));
    }

    // ==========================================
    // Rejected Application Resubmission Tests
    // ==========================================

    public function test_rejected_vendor_can_edit_and_resubmit(): void
    {
        $user = User::factory()->create();
        VendorApplication::factory()
            ->for($user)
            ->withGhanaCard()
            ->unregisteredVendor()
            ->withUnregisteredDocuments()
            ->rejected()
            ->create(['completed_step' => 4]);

        // User should be able to update Ghana Card
        $response = $this->actingAs($user)->postJson('/api/v1/vendor-registration/step-1/ghana-card', [
            'ghana_card_front' => $this->createFakeImage('new_front.jpg'),
            'ghana_card_back' => $this->createFakeImage('new_back.jpg'),
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('vendor_applications', [
            'user_id' => $user->id,
            'status' => VendorApplication::STATUS_REJECTED, // Still rejected until resubmitted
        ]);
    }
}
