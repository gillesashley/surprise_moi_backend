<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/transaction*' => Http::response([
                'status' => true,
                'data' => [
                    ['reference' => 'REF_001', 'amount' => 50000, 'status' => 'success', 'created_at' => '2026-03-20T10:00:00Z'],
                ],
                'meta' => ['total' => 1, 'page' => 1, 'pageCount' => 1],
            ], 200),
        ]);
    }

    public function test_transactions_tab_renders(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.transactions'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('treasury/index')
                ->where('tab', 'transactions')
                ->has('transactions')
            );
    }

    public function test_transactions_tab_accepts_filters(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.transactions', ['from' => '2026-03-01', 'to' => '2026-03-20', 'status' => 'success']))
            ->assertOk();
    }
}
