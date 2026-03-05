<?php

namespace Tests\Unit\Services;

use App\Services\PaystackService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaystackServiceTransferTest extends TestCase
{
    protected PaystackService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PaystackService::class);
    }

    public function test_list_banks_returns_banks_for_ghana(): void
    {
        Http::fake([
            'https://api.paystack.co/bank?currency=GHS' => Http::response([
                'status' => true,
                'message' => 'Banks retrieved',
                'data' => [
                    ['name' => 'MTN Mobile Money', 'code' => 'MTN', 'type' => 'mobile_money'],
                    ['name' => 'GCB Bank', 'code' => 'GH010', 'type' => 'ghipss'],
                ],
            ], 200),
        ]);

        $result = $this->service->listBanks('GHS');

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
    }

    public function test_resolve_account_number_returns_account_details(): void
    {
        Http::fake([
            'https://api.paystack.co/bank/resolve*' => Http::response([
                'status' => true,
                'message' => 'Account number resolved',
                'data' => [
                    'account_number' => '0241234567',
                    'account_name' => 'John Doe',
                ],
            ], 200),
        ]);

        $result = $this->service->resolveAccountNumber('0241234567', 'MTN');

        $this->assertTrue($result['success']);
        $this->assertEquals('John Doe', $result['data']['account_name']);
    }

    public function test_create_transfer_recipient_returns_recipient_code(): void
    {
        Http::fake([
            'https://api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'message' => 'Transfer Recipient created',
                'data' => [
                    'recipient_code' => 'RCP_1234567890',
                    'type' => 'mobile_money',
                    'name' => 'John Doe',
                ],
            ], 200),
        ]);

        $result = $this->service->createTransferRecipient(
            type: 'mobile_money',
            name: 'John Doe',
            accountNumber: '0241234567',
            bankCode: 'MTN',
            currency: 'GHS'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('RCP_1234567890', $result['data']['recipient_code']);
    }

    public function test_check_balance_returns_balances(): void
    {
        Http::fake([
            'https://api.paystack.co/balance' => Http::response([
                'status' => true,
                'message' => 'Balances retrieved',
                'data' => [
                    ['currency' => 'GHS', 'balance' => 500000],
                ],
            ], 200),
        ]);

        $result = $this->service->checkBalance();

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('data', $result);
    }

    public function test_initiate_transfer_returns_transfer_code(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer requires OTP to continue',
                'data' => [
                    'transfer_code' => 'TRF_1234567890',
                    'reference' => 'PYT-TEST123',
                    'status' => 'otp',
                    'id' => 12345,
                ],
            ], 200),
        ]);

        $result = $this->service->initiateTransfer(
            amount: 10000,
            recipientCode: 'RCP_1234567890',
            reason: 'Payout #PYT-TEST for Vendor',
            reference: 'PYT-TEST123'
        );

        $this->assertTrue($result['success']);
        $this->assertEquals('TRF_1234567890', $result['data']['transfer_code']);
        $this->assertEquals('otp', $result['data']['status']);
    }

    public function test_finalize_transfer_completes_transfer(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer/finalize_transfer' => Http::response([
                'status' => true,
                'message' => 'Transfer has been queued',
                'data' => [
                    'transfer_code' => 'TRF_1234567890',
                    'status' => 'pending',
                ],
            ], 200),
        ]);

        $result = $this->service->finalizeTransfer('TRF_1234567890', '123456');

        $this->assertTrue($result['success']);
    }

    public function test_verify_transfer_returns_status(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer/verify/PYT-TEST123' => Http::response([
                'status' => true,
                'message' => 'Transfer retrieved',
                'data' => [
                    'status' => 'success',
                    'reference' => 'PYT-TEST123',
                    'amount' => 10000,
                ],
            ], 200),
        ]);

        $result = $this->service->verifyTransfer('PYT-TEST123');

        $this->assertTrue($result['success']);
        $this->assertEquals('success', $result['data']['status']);
    }

    public function test_initiate_transfer_fails_with_insufficient_balance(): void
    {
        Http::fake([
            'https://api.paystack.co/transfer' => Http::response([
                'status' => false,
                'message' => 'Your balance is not enough to fulfill this request',
            ], 400),
        ]);

        $result = $this->service->initiateTransfer(
            amount: 10000000,
            recipientCode: 'RCP_1234567890',
            reason: 'Payout',
            reference: 'PYT-TEST456'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('balance', strtolower($result['message']));
    }

    public function test_delete_transfer_recipient_succeeds(): void
    {
        Http::fake([
            'https://api.paystack.co/transferrecipient/RCP_1234567890' => Http::response([
                'status' => true,
                'message' => 'Transfer recipient set as inactive',
            ], 200),
        ]);

        $result = $this->service->deleteTransferRecipient('RCP_1234567890');

        $this->assertTrue($result['success']);
    }
}
