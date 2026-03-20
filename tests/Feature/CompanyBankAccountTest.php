<?php

namespace Tests\Feature;

use App\Models\CompanyBankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyBankAccountTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);

        Http::fake([
            'api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [['balance' => 1000000, 'currency' => 'GHS']],
                'meta' => [],
            ], 200),
        ]);
    }

    public function test_super_admin_can_verify_bank_account(): void
    {
        Http::fake([
            '*/bank/resolve*' => Http::response([
                'status' => true,
                'data' => [
                    'account_number' => '0123456789',
                    'account_name' => 'John Doe',
                ],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.bank-account.verify'), [
                'account_number' => '0123456789',
                'bank_code' => '058',
            ]);

        $response->assertOk();
    }

    public function test_super_admin_can_save_bank_account(): void
    {
        Http::fake([
            '*/transferrecipient' => Http::response([
                'status' => true,
                'data' => ['recipient_code' => 'RCP_abc123'],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('treasury.bank-account.save'), [
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'GTBank',
                'account_name' => 'John Doe',
            ]);

        $response->assertRedirect(route('treasury.transfers'));

        $this->assertDatabaseHas('company_bank_accounts', [
            'account_number' => '0123456789',
            'bank_code' => '058',
            'is_active' => true,
        ]);
    }

    public function test_only_one_account_can_be_active(): void
    {
        Http::fake([
            '*/transferrecipient' => Http::response([
                'status' => true,
                'data' => ['recipient_code' => 'RCP_xyz789'],
            ], 200),
        ]);

        $first = CompanyBankAccount::factory()->active()->create([
            'added_by' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('treasury.bank-account.save'), [
                'account_number' => '9999999999',
                'bank_code' => '044',
                'bank_name' => 'Access Bank',
                'account_name' => 'Jane Doe',
            ]);

        $this->assertFalse($first->fresh()->is_active);
        $this->assertDatabaseHas('company_bank_accounts', [
            'account_number' => '9999999999',
            'is_active' => true,
        ]);
    }

    public function test_admin_cannot_save_bank_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('treasury.bank-account.save'), [
                'account_number' => '0123456789',
                'bank_code' => '058',
                'bank_name' => 'GTBank',
                'account_name' => 'John Doe',
            ])
            ->assertForbidden();
    }
}
