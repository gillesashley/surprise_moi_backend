<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use App\Models\VendorApplication;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FieldAgentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agent = User::factory()->create(['role' => 'field_agent']);
        $code = new ReferralCode(['influencer_id' => $this->agent->id, 'is_active' => true]);
        $code->prefix = ReferralCode::getPrefixForRole('field_agent');
        $code->save();
    }

    public function test_payload_includes_the_agents_referral_code(): void
    {
        $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->component('field-agent/dashboard')
            ->has('referralCode', fn ($code) => $code
                ->where('code', fn ($c) => str_starts_with($c, 'FA-'))
                ->etc()
            )
        );
    }

    public function test_payload_lazily_creates_a_referral_code_when_agent_has_none(): void
    {
        $agentWithoutCode = User::factory()->create(['role' => 'field_agent']);

        $response = $this->actingAs($agentWithoutCode)->get('/field-agent/dashboard');

        $response->assertOk();
        $this->assertDatabaseHas('referral_codes', ['influencer_id' => $agentWithoutCode->id]);
    }

    public function test_stats_include_only_vendors_who_used_this_agents_referral_code(): void
    {
        $otherAgent = User::factory()->create(['role' => 'field_agent']);
        $otherCode = new ReferralCode(['influencer_id' => $otherAgent->id, 'is_active' => true]);
        $otherCode->prefix = ReferralCode::getPrefixForRole('field_agent');
        $otherCode->save();

        $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

        VendorApplication::factory()->approved()->create(['referral_code_id' => $myCode->id]);
        VendorApplication::factory()->approved()->create(['referral_code_id' => $myCode->id]);
        VendorApplication::factory()->approved()->create(['referral_code_id' => $otherCode->id]);
        VendorApplication::factory()->approved()->create(['referral_code_id' => null]);

        $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('vendorStats.total', 2)
            ->where('vendorStats.approved', 2)
            ->etc()
        );
    }

    public function test_total_vendors_ignores_period_filter(): void
    {
        $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

        VendorApplication::factory()->approved()->create([
            'referral_code_id' => $myCode->id,
            'created_at' => Carbon::now()->subYear(),
        ]);

        $week = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=week');
        $month = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=month');

        $week->assertInertia(fn ($page) => $page->where('vendorStats.total', 1)->etc());
        $month->assertInertia(fn ($page) => $page->where('vendorStats.total', 1)->etc());
    }

    public function test_period_filter_scopes_pending_approved_rejected_counts(): void
    {
        // Freeze time to a late-month day so ->subDays(20) still lands within startOfMonth()
        // (near the 1st-20th of a month, a 20-day-old row would spill into the previous month).
        $this->travelTo(Carbon::create(2026, 4, 25, 12, 0, 0));

        $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

        VendorApplication::factory()->pending()->create([
            'referral_code_id' => $myCode->id,
            'created_at' => Carbon::now()->startOfDay()->addHour(),
        ]);
        VendorApplication::factory()->approved()->create([
            'referral_code_id' => $myCode->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);
        VendorApplication::factory()->rejected()->create([
            'referral_code_id' => $myCode->id,
            'created_at' => Carbon::now()->subDays(20),
        ]);

        $today = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=today');
        $today->assertInertia(fn ($page) => $page
            ->where('vendorStats.pending', 1)
            ->where('vendorStats.approved', 0)
            ->where('vendorStats.rejected', 0)
            ->etc()
        );

        $week = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=week');
        $week->assertInertia(fn ($page) => $page
            ->where('vendorStats.pending', 1)
            ->where('vendorStats.approved', 1)
            ->where('vendorStats.rejected', 0)
            ->etc()
        );

        $month = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=month');
        $month->assertInertia(fn ($page) => $page
            ->where('vendorStats.pending', 1)
            ->where('vendorStats.approved', 1)
            ->where('vendorStats.rejected', 1)
            ->etc()
        );
    }

    public function test_invalid_period_falls_back_to_week(): void
    {
        // Freeze to mid-week so subDays(2) is reliably after startOfWeek().
        $this->travelTo(Carbon::create(2026, 4, 22, 12, 0, 0));

        $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();
        VendorApplication::factory()->approved()->create([
            'referral_code_id' => $myCode->id,
            'created_at' => Carbon::now()->subDays(2),
        ]);

        $response = $this->actingAs($this->agent)->get('/field-agent/dashboard?period=garbage');

        $response->assertInertia(fn ($page) => $page
            ->where('period', 'week')
            ->where('vendorStats.approved', 1)
            ->etc()
        );
    }

    public function test_recent_vendors_returns_last_five_in_reverse_chronological_order(): void
    {
        $myCode = ReferralCode::where('influencer_id', $this->agent->id)->first();

        $ids = [];
        foreach (range(1, 7) as $i) {
            $app = VendorApplication::factory()->pending()->create([
                'referral_code_id' => $myCode->id,
                'created_at' => Carbon::now()->subMinutes($i),
            ]);
            $ids[] = $app->id;
        }

        $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->has('recentVendors', 5)
            ->where('recentVendors.0.id', $ids[0])
            ->where('recentVendors.4.id', $ids[4])
            ->etc()
        );
    }

    public function test_active_target_card_omitted_when_no_active_target(): void
    {
        $response = $this->actingAs($this->agent)->get('/field-agent/dashboard');

        $response->assertInertia(fn ($page) => $page
            ->where('activeTarget', null)
            ->etc()
        );
    }
}
