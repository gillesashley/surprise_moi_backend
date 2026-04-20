<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OverrideVendorVisit
{
    public function execute(
        VendorVisit $visit,
        User $admin,
        VendorVisitStatus $newResult,
        string $reason,
    ): VendorVisit {
        if (! in_array($newResult, [VendorVisitStatus::Passed, VendorVisitStatus::Failed], true)) {
            throw new InvalidArgumentException('Override result must be Passed or Failed.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('Override reason is required.');
        }

        return DB::transaction(function () use ($visit, $admin, $newResult, $reason) {
            $now = now();

            $updates = [
                'status' => $newResult->value,
                'admin_override_result' => $newResult->value,
                'admin_override_reason' => $reason,
                'admin_override_by' => $admin->id,
                'admin_override_at' => $now,
            ];

            if ($newResult === VendorVisitStatus::Passed) {
                $updates['badge_issued_at'] = $visit->badge_issued_at ?? $now;
                $updates['badge_expires_at'] = $now->copy()->addMonths(
                    (int) config('vendor_visit_checklist.badge_validity_months', 12)
                );
            } else {
                $updates['badge_issued_at'] = null;
                $updates['badge_expires_at'] = null;
            }

            $visit->update($updates);

            return $visit->refresh();
        });
    }
}
