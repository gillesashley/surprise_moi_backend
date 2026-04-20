<?php

namespace App\Observers;

use App\Models\User;
use App\Models\VendorApplication;

class FieldVerificationInvalidationObserver
{
    /** @var array<int, string> */
    private array $userWatchedFields = [
        'business_name',
        'phone',
        'first_name',
        'last_name',
        'name',
    ];

    /** @var array<int, string> */
    private array $vendorApplicationWatchedFields = [
        'ghana_card_front',
        'ghana_card_back',
        'selfie_image',
    ];

    public function userUpdated(User $user): void
    {
        if ($user->field_verified_until === null) {
            return;
        }
        if (! $user->wasChanged($this->userWatchedFields)) {
            return;
        }
        $this->clearBadge($user);
    }

    public function vendorApplicationUpdated(VendorApplication $application): void
    {
        if (! $application->wasChanged($this->vendorApplicationWatchedFields)) {
            return;
        }
        $vendor = $application->user;
        if ($vendor === null || $vendor->field_verified_until === null) {
            return;
        }
        $this->clearBadge($vendor);
    }

    private function clearBadge(User $vendor): void
    {
        User::query()->whereKey($vendor->id)->update([
            'field_verified_at' => null,
            'field_verified_until' => null,
        ]);
    }
}
