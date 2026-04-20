<?php

namespace Tests\Unit\Actions\VendorVisit;

use App\Actions\VendorVisit\StartVendorVisit;
use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StartVendorVisitTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_draft_visit_with_gps_and_started_at(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute(
            vendor: $vendor,
            agent: $agent,
            latitude: 5.56,
            longitude: -0.2,
        );

        $this->assertSame(VendorVisitStatus::Draft, $visit->status);
        $this->assertSame($vendor->id, $visit->vendor_user_id);
        $this->assertSame($agent->id, $visit->field_agent_user_id);
        $this->assertNotNull($visit->started_at);
        $this->assertSame('5.5600000', (string) $visit->visit_latitude);
    }

    public function test_it_seeds_always_items_for_unregistered_vendor(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $keys = $visit->items()->pluck('item_key')->all();

        $this->assertContains('identity.person_matches_ghana_card', $keys);
        $this->assertContains('physical.location_is_real', $keys);
        $this->assertContains('financial.phone_reachable', $keys);
        $this->assertNotContains('documents.business_cert_seen', $keys);
        $this->assertNotContains('documents.tin_seen', $keys);
    }

    public function test_it_seeds_business_cert_item_only_when_vendor_has_cert(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => true,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $keys = $visit->items()->pluck('item_key')->all();
        $this->assertContains('documents.business_cert_seen', $keys);
        $this->assertNotContains('documents.tin_seen', $keys);
    }

    public function test_it_seeds_tin_item_only_when_vendor_has_tin(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => 'C1234567890',
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $visit = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);

        $keys = $visit->items()->pluck('item_key')->all();
        $this->assertContains('documents.tin_seen', $keys);
        $this->assertNotContains('documents.business_cert_seen', $keys);
    }

    public function test_it_resumes_existing_draft_instead_of_creating_duplicate(): void
    {
        $vendor = User::factory()->vendor()->create();
        VendorApplication::factory()->create([
            'user_id' => $vendor->id,
            'status' => 'approved',
            'has_business_certificate' => false,
            'tin_number' => null,
        ]);
        $agent = User::factory()->fieldAgent()->create();

        $first = app(StartVendorVisit::class)->execute($vendor, $agent, 5.56, -0.2);
        $second = app(StartVendorVisit::class)->execute($vendor, $agent, 5.57, -0.3);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, \App\Models\VendorVisit::where('vendor_user_id', $vendor->id)->count());
    }
}
