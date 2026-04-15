<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class UserPayoutDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_null_when_user_has_no_details(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/api/v1/me/payout-details')
            ->assertStatus(200)
            ->assertJsonPath('data', null);
    }

    public function test_store_creates_details_when_paystack_accepts(): void
    {
        $user = User::factory()->create(['name' => 'Kwame Asare']);

        $this->mock(PaystackService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createMobileMoneyRecipient')
                ->once()
                ->andReturn([
                    'recipient_code' => 'RCP_ABC123',
                    'account_name' => 'KWAME ASARE',
                    'raw' => [],
                ]);
        });

        $this->actingAs($user)
            ->postJson('/api/v1/me/payout-details', [
                'mobile_money_number' => '0244123456',
                'mobile_money_provider' => 'mtn',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.is_verified', true)
            ->assertJsonPath('data.account_name', 'KWAME ASARE')
            ->assertJsonPath('data.is_default', true);

        $this->assertDatabaseHas('user_payout_details', [
            'user_id' => $user->id,
            'mobile_money_number' => '0244123456',
            'paystack_recipient_code' => 'RCP_ABC123',
        ]);
    }

    public function test_store_returns_422_when_paystack_rejects(): void
    {
        $user = User::factory()->create();

        $this->mock(PaystackService::class, function (MockInterface $mock) {
            $mock->shouldReceive('createMobileMoneyRecipient')
                ->once()
                ->andThrow(new \RuntimeException('Invalid MoMo number for MTN'));
        });

        $this->actingAs($user)
            ->postJson('/api/v1/me/payout-details', [
                'mobile_money_number' => '0200000000',
                'mobile_money_provider' => 'mtn',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Invalid MoMo number for MTN');

        $this->assertDatabaseCount('user_payout_details', 0);
    }

    public function test_verify_returns_account_name_on_success(): void
    {
        $user = User::factory()->create();

        $this->mock(PaystackService::class, function (MockInterface $mock) {
            $mock->shouldReceive('resolveMobileMoneyAccount')
                ->once()
                ->andReturn(['valid' => true, 'account_name' => 'KWAME ASARE']);
        });

        $this->actingAs($user)
            ->postJson('/api/v1/me/payout-details/verify', [
                'mobile_money_number' => '0244123456',
                'mobile_money_provider' => 'mtn',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.account_name', 'KWAME ASARE');
    }

    public function test_validation_rejects_non_numeric_number(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/v1/me/payout-details', [
                'mobile_money_number' => 'not-a-number',
                'mobile_money_provider' => 'mtn',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mobile_money_number']);
    }

    public function test_unauthenticated_user_cannot_access(): void
    {
        $this->getJson('/api/v1/me/payout-details')->assertStatus(401);
    }
}
