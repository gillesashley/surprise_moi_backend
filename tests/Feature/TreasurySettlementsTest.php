<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasurySettlementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/settlement*' => Http::response([
                'status' => true,
                'data' => [
                    ['settled_date' => '2026-03-19', 'total_amount' => 300000, 'status' => 'success'],
                ],
                'meta' => ['total' => 1],
            ], 200),
        ]);
    }

    public function test_settlements_tab_renders(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($user)
            ->get(route('treasury.settlements'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('treasury/index')
                ->where('tab', 'settlements')
                ->has('settlements')
            );
    }
}
