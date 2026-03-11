<?php

namespace Tests\Feature\Rider;

use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\RiderWithdrawalRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RiderEarningTest extends TestCase
{
    use RefreshDatabase;

    protected Rider $rider;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rider = Rider::factory()->approved()->create();
        $this->token = $this->rider->createToken('rider-app')->plainTextToken;
    }

    public function test_rider_can_view_balance_summary(): void
    {
        RiderEarning::factory()->available()->create([
            'rider_id' => $this->rider->id,
            'amount' => 50.00,
        ]);
        RiderEarning::factory()->available()->create([
            'rider_id' => $this->rider->id,
            'amount' => 30.00,
        ]);
        RiderEarning::factory()->create([
            'rider_id' => $this->rider->id,
            'amount' => 20.00,
            'status' => 'pending',
        ]);

        RiderWithdrawalRequest::factory()->completed()->create([
            'rider_id' => $this->rider->id,
            'amount' => 10.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/rider/v1/earnings');

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'available' => 80.0,
                    'pending' => 20.0,
                    'total_earned' => 100.0,
                    'total_withdrawn' => 10.0,
                ],
            ]);
    }

    public function test_rider_can_view_transactions(): void
    {
        RiderEarning::factory()->count(3)->create([
            'rider_id' => $this->rider->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->getJson('/api/rider/v1/earnings/transactions');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => ['id', 'order_id', 'amount', 'type', 'status', 'available_at', 'created_at'],
                ],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_rider_can_request_withdrawal(): void
    {
        RiderEarning::factory()->available()->create([
            'rider_id' => $this->rider->id,
            'amount' => 100.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/rider/v1/earnings/withdraw', [
                'amount' => 50.00,
                'mobile_money_provider' => 'mtn',
                'mobile_money_number' => '0241234567',
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Withdrawal request submitted. Processing takes 1-24 hours.',
            ])
            ->assertJsonStructure([
                'data' => ['id', 'amount', 'status', 'mobile_money_provider', 'mobile_money_number'],
            ]);

        $this->assertDatabaseHas('rider_withdrawal_requests', [
            'rider_id' => $this->rider->id,
            'amount' => 50.00,
            'mobile_money_provider' => 'mtn',
            'mobile_money_number' => '0241234567',
            'status' => 'pending',
        ]);
    }

    public function test_rider_cannot_withdraw_more_than_available(): void
    {
        RiderEarning::factory()->available()->create([
            'rider_id' => $this->rider->id,
            'amount' => 30.00,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->token}")
            ->postJson('/api/rider/v1/earnings/withdraw', [
                'amount' => 50.00,
                'mobile_money_provider' => 'mtn',
                'mobile_money_number' => '0241234567',
            ]);

        $response->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'message' => 'Insufficient available balance.',
            ]);

        $this->assertDatabaseMissing('rider_withdrawal_requests', [
            'rider_id' => $this->rider->id,
        ]);
    }
}
