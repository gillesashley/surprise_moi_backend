<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [['balance' => 1000000, 'currency' => 'GHS']],
                'meta' => [],
            ], 200),
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('treasury.index'))->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_treasury_overview(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertOk();
    }

    public function test_admin_cannot_access_treasury(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertForbidden();
    }

    public function test_vendor_cannot_access_treasury(): void
    {
        $user = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertRedirect(route('login'));
    }

    public function test_customer_cannot_access_treasury(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)
            ->get(route('treasury.index'))
            ->assertRedirect(route('login'));
    }

    public function test_super_admin_can_access_all_treasury_tabs(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)->get(route('treasury.transactions'))->assertOk();
        $this->actingAs($user)->get(route('treasury.settlements'))->assertOk();
        $this->actingAs($user)->get(route('treasury.transfers'))->assertOk();
    }
}
