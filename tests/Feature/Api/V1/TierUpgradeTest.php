<?php

namespace Tests\Feature\Api\V1;

use App\Models\TierUpgradeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
