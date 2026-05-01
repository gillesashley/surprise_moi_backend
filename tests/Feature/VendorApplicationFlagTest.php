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

    public function test_get_statuses_returns_lifecycle_ordered_array(): void
    {
        // Order matters: the admin status filter dropdown iterates over this array.
        $this->assertSame(
            ['pending', 'under_review', 'flagged', 'approved', 'rejected'],
            VendorApplication::getStatuses(),
        );
    }

    public function test_flagged_scope_returns_only_flagged_applications(): void
    {
        $flagged = VendorApplication::factory()->create(['status' => VendorApplication::STATUS_FLAGGED]);
        VendorApplication::factory()->create(['status' => VendorApplication::STATUS_PENDING]);

        $results = VendorApplication::query()->flagged()->get();

        $this->assertCount(1, $results);
        $this->assertSame($flagged->id, $results->first()->id);
    }

    public function test_flagger_relationship_resolves_to_flagged_by_user_not_application_owner(): void
    {
        $owner = User::factory()->create();
        $flagger = User::factory()->create();
        $app = VendorApplication::factory()->create([
            'user_id' => $owner->id,
            'flagged_by' => $flagger->id,
        ]);

        $this->assertSame($flagger->id, $app->flagger->id);
        $this->assertNotSame($owner->id, $app->flagger->id);
    }
}
