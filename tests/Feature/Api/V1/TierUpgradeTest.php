<?php

namespace Tests\Feature\Api\V1;

use App\Models\TierUpgradeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TierUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = User::factory()->vendor()->create(['vendor_tier' => 2]);
    }

    public function test_tier_upgrade_request_can_be_created(): void
    {
        $request = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $this->assertDatabaseHas('tier_upgrade_requests', [
            'id' => $request->id,
            'vendor_id' => $this->vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
        ]);
    }

    public function test_tier_upgrade_request_factory_states(): void
    {
        $pending = TierUpgradeRequest::factory()->pendingDocument()->create(['vendor_id' => $this->vendor->id]);
        $this->assertTrue($pending->isPendingDocument());
        $this->assertNotNull($pending->payment_reference);

        $review = TierUpgradeRequest::factory()->pendingReview()->create(['vendor_id' => $this->vendor->id]);
        $this->assertTrue($review->isPendingReview());
        $this->assertNotNull($review->business_certificate_document);

        $approved = TierUpgradeRequest::factory()->approved()->create(['vendor_id' => $this->vendor->id]);
        $this->assertTrue($approved->isApproved());

        $rejected = TierUpgradeRequest::factory()->rejected()->create(['vendor_id' => $this->vendor->id]);
        $this->assertTrue($rejected->isRejected());
        $this->assertNotNull($rejected->admin_notes);
    }

    public function test_vendor_relationship(): void
    {
        $request = TierUpgradeRequest::factory()->create(['vendor_id' => $this->vendor->id]);

        $this->assertEquals($this->vendor->id, $request->vendor->id);
    }

    public function test_active_tier_upgrade_request_method(): void
    {
        $this->assertNull($this->vendor->activeTierUpgradeRequest());

        $request = TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $this->assertNotNull($this->vendor->activeTierUpgradeRequest());
        $this->assertEquals($request->id, $this->vendor->activeTierUpgradeRequest()->id);
    }

    public function test_approved_request_is_not_active(): void
    {
        TierUpgradeRequest::factory()->approved()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $this->assertNull($this->vendor->activeTierUpgradeRequest());
    }

    public function test_payment_amount_in_ghs_accessor(): void
    {
        $request = TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
            'payment_amount' => 5000,
        ]);

        $this->assertEquals(50.00, $request->payment_amount_in_ghs);
    }

    public function test_generate_reference_has_tup_prefix(): void
    {
        $reference = TierUpgradeRequest::generateReference();

        $this->assertStringStartsWith('TUP-', $reference);
        $this->assertEquals(20, strlen($reference));
    }

    public function test_can_submit_document_check(): void
    {
        $pendingDoc = TierUpgradeRequest::factory()->pendingDocument()->create(['vendor_id' => $this->vendor->id]);
        $this->assertTrue($pendingDoc->canSubmitDocument());

        $rejected = TierUpgradeRequest::factory()->rejected()->create(['vendor_id' => $this->vendor->id]);
        $this->assertTrue($rejected->canSubmitDocument());

        $pendingReview = TierUpgradeRequest::factory()->pendingReview()->create(['vendor_id' => $this->vendor->id]);
        $this->assertFalse($pendingReview->canSubmitDocument());
    }

    public function test_stale_pending_payment_scope(): void
    {
        $stale = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
            'created_at' => now()->subHours(25),
        ]);

        $fresh = TierUpgradeRequest::factory()->create([
            'vendor_id' => User::factory()->vendor()->create(['vendor_tier' => 2])->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
            'created_at' => now()->subHours(1),
        ]);

        $staleRequests = TierUpgradeRequest::stalePendingPayment()->get();
        $this->assertTrue($staleRequests->contains($stale));
        $this->assertFalse($staleRequests->contains($fresh));
    }

    // --- Summary endpoint tests ---

    public function test_summary_returns_upgrade_fee_for_tier2_vendor(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['upgrade_fee', 'currency', 'current_tier', 'existing_request'],
            ])
            ->assertJsonPath('data.current_tier', 2)
            ->assertJsonPath('data.existing_request', null);
    }

    public function test_summary_shows_existing_rejected_request(): void
    {
        TierUpgradeRequest::factory()->rejected()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.existing_request.status', 'rejected');
    }

    public function test_summary_denied_for_tier1_vendor(): void
    {
        $tier1Vendor = User::factory()->vendor()->create(['vendor_tier' => 1]);

        $response = $this->actingAs($tier1Vendor, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/summary');

        $response->assertStatus(403);
    }

    public function test_summary_denied_for_non_vendor(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($customer, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/summary');

        // Non-vendors will be blocked by either the role:vendor middleware or the tier check
        $response->assertStatus(403);
    }

    // --- Initiate payment tests ---

    public function test_initiate_payment_blocked_when_active_request_exists(): void
    {
        TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/payment/initiate');

        $response->assertStatus(409);
    }

    public function test_initiate_payment_blocked_when_rejected_request_exists(): void
    {
        TierUpgradeRequest::factory()->rejected()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/payment/initiate');

        $response->assertStatus(409);
    }

    // --- Submit document tests ---

    public function test_submit_document_with_pending_document_status(): void
    {
        Storage::fake();

        $upgradeRequest = TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('certificate.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/submit-document', [
                'business_certificate_document' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_review');

        $this->assertDatabaseHas('tier_upgrade_requests', [
            'id' => $upgradeRequest->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_REVIEW,
        ]);
    }

    public function test_submit_document_blocked_when_pending_payment(): void
    {
        TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('certificate.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/submit-document', [
                'business_certificate_document' => $file,
            ]);

        $response->assertStatus(422);
    }

    public function test_resubmit_document_after_rejection(): void
    {
        Storage::fake();

        $upgradeRequest = TierUpgradeRequest::factory()->rejected()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('new-certificate.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/submit-document', [
                'business_certificate_document' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_review');

        $upgradeRequest->refresh();
        $this->assertNull($upgradeRequest->admin_id);
        $this->assertNull($upgradeRequest->admin_notes);
        $this->assertNull($upgradeRequest->reviewed_at);
    }

    public function test_submit_document_validation_rejects_invalid_file(): void
    {
        TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('document.txt', 1024, 'text/plain');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/submit-document', [
                'business_certificate_document' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('business_certificate_document');
    }

    // --- Status endpoint tests ---

    public function test_status_returns_current_request(): void
    {
        $upgradeRequest = TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $upgradeRequest->id)
            ->assertJsonPath('data.status', 'pending_review');
    }

    public function test_status_returns_null_when_no_request(): void
    {
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/status');

        $response->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    // --- Cancel tests ---

    public function test_cancel_pending_payment_request(): void
    {
        $upgradeRequest = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson('/api/v1/vendor/upgrade-tier/cancel');

        $response->assertStatus(200);
        $this->assertDatabaseMissing('tier_upgrade_requests', ['id' => $upgradeRequest->id]);
    }

    public function test_cancel_blocked_when_not_pending_payment(): void
    {
        TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->deleteJson('/api/v1/vendor/upgrade-tier/cancel');

        $response->assertStatus(422);
    }

    // --- Notification tests ---

    public function test_submit_document_notifies_admins(): void
    {
        Storage::fake();
        Notification::fake();

        $admin = User::factory()->admin()->create();

        TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('certificate.pdf', 1024, 'application/pdf');

        $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/submit-document', [
                'business_certificate_document' => $file,
            ]);

        Notification::assertSentTo($admin, \App\Notifications\TierUpgradeSubmittedNotification::class);
    }

    // --- Webhook tests ---

    public function test_webhook_verifies_pending_payment(): void
    {
        $upgradeRequest = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
            'payment_reference' => 'TUP-TESTREF123456789',
            'payment_amount' => 5000,
            'payment_currency' => 'GHS',
        ]);

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'TUP-TESTREF123456789',
                'status' => 'success',
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, config('services.paystack.secret_key', ''));

        $response = $this->postJson('/api/v1/vendor/upgrade-tier/webhook', json_decode($payload, true), [
            'x-paystack-signature' => $signature,
        ]);

        $response->assertStatus(200);

        $upgradeRequest->refresh();
        $this->assertEquals(TierUpgradeRequest::STATUS_PENDING_DOCUMENT, $upgradeRequest->status);
        $this->assertNotNull($upgradeRequest->payment_verified_at);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => ['reference' => 'TUP-TESTREF123456789'],
        ]);

        $response = $this->postJson('/api/v1/vendor/upgrade-tier/webhook', json_decode($payload, true), [
            'x-paystack-signature' => 'invalid-signature',
        ]);

        $response->assertStatus(400);
    }

    // --- Full happy path test ---

    public function test_full_upgrade_happy_path(): void
    {
        Storage::fake();
        Notification::fake();

        // Step 1: Get summary
        $response = $this->actingAs($this->vendor, 'sanctum')
            ->getJson('/api/v1/vendor/upgrade-tier/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.current_tier', 2)
            ->assertJsonPath('data.existing_request', null);

        // Step 2: Create a pending_document request (simulating post-payment)
        $upgradeRequest = TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
        ]);

        // Step 3: Submit document
        $file = \Illuminate\Http\UploadedFile::fake()->create('certificate.pdf', 1024, 'application/pdf');

        $response = $this->actingAs($this->vendor, 'sanctum')
            ->postJson('/api/v1/vendor/upgrade-tier/submit-document', [
                'business_certificate_document' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'pending_review');

        // Step 4: Admin approves
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/vendor/upgrade-tier/{$upgradeRequest->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'approved');

        // Step 5: Verify vendor is now Tier 1
        $this->vendor->refresh();
        $this->assertEquals(1, $this->vendor->vendor_tier);

        Notification::assertSentTo($this->vendor, \App\Notifications\TierUpgradeApprovedNotification::class);
    }

    // --- Expire stale command test ---

    public function test_expire_stale_command(): void
    {
        $stale = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
            'created_at' => now()->subHours(25),
        ]);

        $fresh = TierUpgradeRequest::factory()->create([
            'vendor_id' => User::factory()->vendor()->create(['vendor_tier' => 2])->id,
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
            'created_at' => now()->subHours(1),
        ]);

        $this->artisan('tier-upgrade:expire-stale')
            ->assertSuccessful();

        $this->assertDatabaseMissing('tier_upgrade_requests', ['id' => $stale->id]);
        $this->assertDatabaseHas('tier_upgrade_requests', ['id' => $fresh->id]);
    }
}
