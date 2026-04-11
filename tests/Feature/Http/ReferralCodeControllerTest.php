<?php

namespace Tests\Feature\Http;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReferralCodeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_users_by_role_returns_matching_role_only(): void
    {
        User::factory()->count(3)->create(['role' => 'customer']);
        User::factory()->count(2)->create(['role' => 'vendor']);

        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/referral-codes/users-by-role?role=customer');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_users_by_role_customer_also_matches_null_role_users(): void
    {
        User::factory()->create(['role' => 'customer']);

        // The role column is NOT NULL in the schema. However, the usersByRole query
        // defensively handles null-role legacy users (pre-dating the NOT NULL constraint).
        // Since PostgreSQL DDL is transactional, DROP NOT NULL is rolled back by
        // RefreshDatabase's transaction wrapper, so no restore statement is needed.
        DB::statement('ALTER TABLE users ALTER COLUMN role DROP NOT NULL');

        DB::table('users')->insert([
            'name' => 'Legacy User',
            'email' => 'legacy@example.com',
            'password' => bcrypt('password'),
            'role' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/referral-codes/users-by-role?role=customer');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_users_by_role_search_filters_by_name(): void
    {
        User::factory()->create(['role' => 'customer', 'name' => 'Alice Smith']);
        User::factory()->create(['role' => 'customer', 'name' => 'Bob Jones']);
        User::factory()->create(['role' => 'customer', 'name' => 'Charlie Alice']);

        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/referral-codes/users-by-role?role=customer&q=alice');

        $response->assertOk();
        $response->assertJsonCount(2, 'data'); // Alice Smith + Charlie Alice
    }

    public function test_users_by_role_search_filters_by_email(): void
    {
        User::factory()->create(['role' => 'customer', 'email' => 'findme@example.com']);
        User::factory()->create(['role' => 'customer', 'email' => 'other@example.com']);

        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/referral-codes/users-by-role?role=customer&q=findme');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_users_by_role_requires_authenticated_admin(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        // The 'dashboard' middleware (EnsureDashboardAccess) redirects non-admin users
        // before the controller policy check fires, resulting in a 302 redirect.
        $response = $this->actingAs($customer)
            ->get('/dashboard/referral-codes/users-by-role?role=customer');

        $response->assertRedirect();
    }

    public function test_users_by_role_rejects_unknown_role(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/referral-codes/users-by-role?role=hackerman');

        $response->assertUnprocessable();
    }

    public function test_users_by_role_limits_results_to_twenty(): void
    {
        User::factory()->count(30)->create(['role' => 'customer']);

        $response = $this->actingAs($this->admin)
            ->getJson('/dashboard/referral-codes/users-by-role?role=customer');

        $response->assertOk();
        $response->assertJsonCount(20, 'data');
    }

    public function test_users_by_role_forbidden_for_influencer(): void
    {
        $influencer = User::factory()->create(['role' => 'influencer']);

        $response = $this->actingAs($influencer)
            ->getJson('/dashboard/referral-codes/users-by-role?role=admin');

        // Influencers pass the referral-code create policy (for the self-service
        // API) but must not be able to enumerate admin accounts via this endpoint.
        // Because EnsureDashboardAccess may redirect them before the explicit
        // abort_if fires, accept either 302 redirect or 403 forbidden.
        $this->assertTrue(
            $response->status() === 403 || $response->status() === 302,
            "Expected 403 or 302, got {$response->status()}"
        );
    }

    public function test_admin_can_create_code_for_customer(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/referral-codes', [
                'influencer_id' => $customer->id,
                'description' => 'My customer code',
                'registration_bonus' => 50,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('referral_codes', [
            'influencer_id' => $customer->id,
            'description' => 'My customer code',
        ]);
    }

    public function test_store_accepts_payload_without_commission_fields(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($this->admin)
            ->post('/dashboard/referral-codes', [
                'influencer_id' => $customer->id,
                'registration_bonus' => 25,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('referral_codes', [
            'influencer_id' => $customer->id,
            'registration_bonus' => 25,
        ]);
    }

    public function test_create_page_does_not_pass_influencers_prop(): void
    {
        $response = $this->actingAs($this->admin)->get('/dashboard/referral-codes/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('referral-codes/create')
            ->missing('influencers')
        );
    }

    public function test_update_silently_drops_commission_fields_if_submitted(): void
    {
        $customer = \App\Models\User::factory()->create(['role' => 'customer']);
        $code = \App\Models\ReferralCode::factory()->create([
            'influencer_id' => $customer->id,
            'commission_rate' => 7.5,
            'commission_duration_months' => 6,
        ]);

        $response = $this->actingAs($this->admin)
            ->put("/dashboard/referral-codes/{$code->id}", [
                'description' => 'updated',
                'registration_bonus' => 60,
                'is_active' => true,
                // Try to sneak commission fields in — they should be ignored
                'commission_rate' => 99,
                'commission_duration_months' => 999,
            ]);

        $response->assertRedirect();

        $fresh = $code->fresh();
        // Allowed fields updated
        $this->assertEquals('updated', $fresh->description);
        $this->assertEquals(60.00, $fresh->registration_bonus);
        // Commission fields unchanged from their original values
        $this->assertEquals(7.5, (float) $fresh->commission_rate);
        $this->assertEquals(6, $fresh->commission_duration_months);
    }
}
