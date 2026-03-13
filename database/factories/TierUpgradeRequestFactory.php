<?php

namespace Database\Factories;

use App\Models\TierUpgradeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TierUpgradeRequestFactory extends Factory
{
    protected $model = TierUpgradeRequest::class;

    public function definition(): array
    {
        return [
            'vendor_id' => User::factory()->vendor(),
            'status' => TierUpgradeRequest::STATUS_PENDING_PAYMENT,
        ];
    }

    public function pendingDocument(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TierUpgradeRequest::STATUS_PENDING_DOCUMENT,
            'payment_reference' => TierUpgradeRequest::generateReference(),
            'payment_amount' => 5000,
            'payment_currency' => 'GHS',
            'payment_verified_at' => now(),
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TierUpgradeRequest::STATUS_PENDING_REVIEW,
            'payment_reference' => TierUpgradeRequest::generateReference(),
            'payment_amount' => 5000,
            'payment_currency' => 'GHS',
            'payment_verified_at' => now(),
            'business_certificate_document' => 'tier-upgrades/business-certificates/1/test.pdf',
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TierUpgradeRequest::STATUS_APPROVED,
            'payment_reference' => TierUpgradeRequest::generateReference(),
            'payment_amount' => 5000,
            'payment_currency' => 'GHS',
            'payment_verified_at' => now(),
            'business_certificate_document' => 'tier-upgrades/business-certificates/1/test.pdf',
            'admin_id' => User::factory()->admin(),
            'reviewed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TierUpgradeRequest::STATUS_REJECTED,
            'payment_reference' => TierUpgradeRequest::generateReference(),
            'payment_amount' => 5000,
            'payment_currency' => 'GHS',
            'payment_verified_at' => now(),
            'business_certificate_document' => 'tier-upgrades/business-certificates/1/test.pdf',
            'admin_id' => User::factory()->admin(),
            'admin_notes' => 'Document is illegible',
            'reviewed_at' => now(),
        ]);
    }
}
