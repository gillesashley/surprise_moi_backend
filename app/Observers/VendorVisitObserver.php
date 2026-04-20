<?php

namespace App\Observers;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;

class VendorVisitObserver
{
    public function saved(VendorVisit $visit): void
    {
        if (! $visit->wasRecentlyCreated && ! $this->transitioned($visit)) {
            return;
        }

        $this->syncBadgeForVendor($visit->vendor_user_id);
    }

    public function deleted(VendorVisit $visit): void
    {
        $this->syncBadgeForVendor($visit->vendor_user_id);
    }

    private function transitioned(VendorVisit $visit): bool
    {
        return $visit->wasChanged(['status', 'badge_expires_at', 'admin_override_result']);
    }

    private function syncBadgeForVendor(int $vendorUserId): void
    {
        $active = VendorVisit::query()
            ->where('vendor_user_id', $vendorUserId)
            ->where('status', VendorVisitStatus::Passed->value)
            ->whereNotNull('badge_expires_at')
            ->orderByDesc('badge_expires_at')
            ->first();

        User::query()->whereKey($vendorUserId)->update([
            'field_verified_at' => $active?->badge_issued_at,
            'field_verified_until' => $active?->badge_expires_at,
        ]);
    }
}
