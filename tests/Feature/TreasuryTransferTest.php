<?php

namespace Tests\Feature;

use App\Models\CompanyBankAccount;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TreasuryTransferTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected CompanyBankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->bankAccount = CompanyBankAccount::factory()->active()->create([
            'added_by' => $this->superAdmin->id,
            'paystack_recipient_code' => 'RCP_test123',
        ]);
    }

    public function test_super_admin_can_initiate_transfer(): void
    {
        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 500000, 'currency' => 'GHS']],
            ], 200),
            '*/transfer' => Http::response([
                'status' => true,
                'data' => ['transfer_code' => 'TRF_abc123'],
                'message' => 'Transfer requires OTP',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.initiate'), [
                'amount' => 1000.00,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'transfer_code' => 'TRF_abc123']);

        $this->assertDatabaseHas('treasury_transfers', [
            'initiated_by' => $this->superAdmin->id,
            'amount' => '1000.00',
            'amount_in_pesewas' => 100000,
            'status' => TreasuryTransfer::STATUS_OTP_REQUIRED,
        ]);
    }

    public function test_transfer_rejected_when_amount_exceeds_balance(): void
    {
        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 5000, 'currency' => 'GHS']],
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('treasury.transfer.initiate'), [
                'amount' => 1000.00,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_transfer_rejected_without_active_bank_account(): void
    {
        $this->bankAccount->update(['is_active' => false]);

        $response = $this->actingAs($this->superAdmin)
            ->post(route('treasury.transfer.initiate'), [
                'amount' => 100.00,
            ]);

        $response->assertSessionHasErrors('amount');
    }

    public function test_super_admin_can_finalize_transfer_with_otp(): void
    {
        $transfer = TreasuryTransfer::factory()->otpRequired()->create([
            'company_bank_account_id' => $this->bankAccount->id,
            'initiated_by' => $this->superAdmin->id,
            'paystack_transfer_code' => 'TRF_finalize123',
        ]);

        Http::fake([
            '*/transfer/finalize_transfer' => Http::response([
                'status' => true,
                'data' => ['status' => 'processing'],
                'message' => 'Transfer is being processed',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.finalize'), [
                'transfer_code' => 'TRF_finalize123',
                'otp' => '123456',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertEquals(
            TreasuryTransfer::STATUS_PROCESSING,
            $transfer->fresh()->status
        );
    }

    public function test_super_admin_can_resend_otp(): void
    {
        Http::fake([
            '*/transfer/resend_otp' => Http::response([
                'status' => true,
                'message' => 'OTP resent',
            ], 200),
        ]);

        $response = $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.resend-otp'), [
                'transfer_code' => 'TRF_resend123',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_treasury_transfer_audit_record_created_on_failure(): void
    {
        Http::fake([
            '*/balance' => Http::response([
                'status' => true,
                'data' => [['balance' => 500000, 'currency' => 'GHS']],
            ], 200),
            '*/transfer' => Http::response([
                'status' => false,
                'message' => 'Transfer failed',
            ], 400),
        ]);

        $this->actingAs($this->superAdmin)
            ->postJson(route('treasury.transfer.initiate'), [
                'amount' => 1000.00,
            ]);

        $this->assertDatabaseHas('treasury_transfers', [
            'initiated_by' => $this->superAdmin->id,
            'status' => TreasuryTransfer::STATUS_FAILED,
        ]);
    }

    public function test_admin_cannot_initiate_transfer(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->postJson(route('treasury.transfer.initiate'), [
                'amount' => 100.00,
            ])
            ->assertForbidden();
    }
}
