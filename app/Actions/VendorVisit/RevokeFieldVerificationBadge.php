<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class RevokeFieldVerificationBadge
{
    public function execute(VendorVisit $visit, User $admin, string $reason): VendorVisit
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Revocation reason is required.');
        }

        if ($visit->status !== VendorVisitStatus::Passed) {
            throw new RuntimeException('Only a passed visit can be revoked.');
        }

        return DB::transaction(function () use ($visit, $admin, $reason) {
            $visit->update([
                'status' => VendorVisitStatus::Revoked->value,
                'admin_override_result' => VendorVisitStatus::Revoked->value,
                'admin_override_reason' => $reason,
                'admin_override_by' => $admin->id,
                'admin_override_at' => now(),
                'badge_issued_at' => null,
                'badge_expires_at' => null,
            ]);

            return $visit->refresh();
        });
    }
}
