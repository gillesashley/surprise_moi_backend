<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);

        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 1500000, 'currency' => 'GHS']],
            ], 200),
            '*/transaction/totals*' => Http::response([
                'status' => true,
                'data' => [
                    'total_transactions' => 150,
                    'total_volume' => 5000000,
                    'pending_amount' => 200000,
                ],
            ], 200),
            '*/transaction*' => Http::response([
                'status' => true,
                'data' => [
                    ['reference' => 'REF_001', 'amount' => 50000, 'status' => 'success', 'created_at' => '2026-03-20T10:00:00Z'],
                ],
                'meta' => ['total' => 1],
            ], 200),
        ]);
    }

    public function test_overview_tab_renders_with_balance_and_totals(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('treasury.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('treasury/index')
                ->has('balance')
                ->has('totals')
                ->has('recentTransactions')
                ->where('tab', 'overview')
            );
    }

    public function test_refresh_clears_treasury_cache(): void
    {
        Cache::put('treasury:balance', ['data' => 'cached'], 600);

        $this->actingAs($this->superAdmin)
            ->post(route('treasury.refresh'))
            ->assertRedirect();

        $this->assertNull(Cache::get('treasury:balance'));
    }
}
