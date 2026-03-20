<?php

namespace Database\Factories;

use App\Models\CompanyBankAccount;
use App\Models\TreasuryTransfer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasuryTransferFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 100, 50000);

        return [
            'company_bank_account_id' => CompanyBankAccount::factory(),
            'initiated_by' => User::factory(),
            'amount' => $amount,
            'amount_in_pesewas' => (int) ($amount * 100),
            'paystack_transfer_code' => 'TRF_'.fake()->bothify('??????????'),
            'paystack_reference' => TreasuryTransfer::generateReference(),
            'status' => TreasuryTransfer::STATUS_PENDING,
            'paystack_response' => null,
            'completed_at' => null,
        ];
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_SUCCESS,
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_FAILED,
            'paystack_response' => ['message' => 'Transfer failed'],
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_PROCESSING,
        ]);
    }

    public function otpRequired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TreasuryTransfer::STATUS_OTP_REQUIRED,
        ]);
    }
}
