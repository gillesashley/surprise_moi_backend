<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VendorApplicationFlagTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_flagged_constant_is_defined(): void
    {
        $this->assertSame('flagged', VendorApplication::STATUS_FLAGGED);
    }

    public function test_get_statuses_includes_flagged(): void
    {
        $this->assertContains(VendorApplication::STATUS_FLAGGED, VendorApplication::getStatuses());
    }

    public function test_flagged_scope_returns_only_flagged_applications(): void
    {
        $flagged = VendorApplication::factory()->create(['status' => VendorApplication::STATUS_FLAGGED]);
        VendorApplication::factory()->create(['status' => VendorApplication::STATUS_PENDING]);

        $results = VendorApplication::query()->flagged()->get();

        $this->assertCount(1, $results);
        $this->assertSame($flagged->id, $results->first()->id);
    }

    public function test_flagger_relationship_returns_user(): void
    {
        $reviewer = User::factory()->create();
        $app = VendorApplication::factory()->create(['flagged_by' => $reviewer->id]);

        $this->assertSame($reviewer->id, $app->flagger->id);
    }
}
