<?php

namespace Tests\Feature;

use App\Models\TierUpgradeRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireStaleTierUpgradeRequestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private User $vendor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vendor = User::factory()->vendor()->create(['vendor_tier' => 2]);
    }

    public function test_it_deletes_stale_pending_payment_requests(): void
    {
        $stale = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subHours(25),
        ]);

        $this->artisan('tier-upgrade:expire-stale')
            ->expectsOutputToContain('Expired 1 stale tier upgrade request(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('tier_upgrade_requests', ['id' => $stale->id]);
    }

    public function test_it_does_not_delete_recent_pending_payment_requests(): void
    {
        $recent = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subHours(23),
        ]);

        $this->artisan('tier-upgrade:expire-stale')
            ->expectsOutputToContain('Expired 0 stale tier upgrade request(s).')
            ->assertSuccessful();

        $this->assertDatabaseHas('tier_upgrade_requests', ['id' => $recent->id]);
    }

    public function test_it_does_not_delete_requests_in_other_statuses(): void
    {
        TierUpgradeRequest::factory()->pendingDocument()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subHours(48),
        ]);

        TierUpgradeRequest::factory()->pendingReview()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subHours(48),
        ]);

        TierUpgradeRequest::factory()->approved()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subHours(48),
        ]);

        $this->artisan('tier-upgrade:expire-stale')
            ->expectsOutputToContain('Expired 0 stale tier upgrade request(s).')
            ->assertSuccessful();

        $this->assertDatabaseCount('tier_upgrade_requests', 3);
    }

    public function test_it_respects_custom_hours_option(): void
    {
        $request = TierUpgradeRequest::factory()->create([
            'vendor_id' => $this->vendor->id,
            'created_at' => now()->subHours(5),
        ]);

        $this->artisan('tier-upgrade:expire-stale --hours=4')
            ->expectsOutputToContain('Expired 1 stale tier upgrade request(s).')
            ->assertSuccessful();

        $this->assertDatabaseMissing('tier_upgrade_requests', ['id' => $request->id]);
    }

    public function test_it_outputs_zero_when_nothing_to_expire(): void
    {
        $this->artisan('tier-upgrade:expire-stale')
            ->expectsOutputToContain('Expired 0 stale tier upgrade request(s).')
            ->assertSuccessful();
    }
}
