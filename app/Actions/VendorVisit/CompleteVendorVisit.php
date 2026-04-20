<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitItemCriticality;
use App\Enums\VendorVisitStatus;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;

class CompleteVendorVisit
{
    public function execute(VendorVisit $visit): VendorVisit
    {
        if ($visit->status->isTerminal()) {
            return $visit;
        }

        return DB::transaction(function () use ($visit) {
            $visit->loadMissing('items');

            $outcome = $this->computeOutcome($visit);
            $now = now();

            $updates = [
                'status' => $outcome->value,
                'computed_result' => $outcome->value,
                'submitted_at' => $now,
            ];

            if ($outcome === VendorVisitStatus::Passed) {
                $updates['badge_issued_at'] = $now;
                $updates['badge_expires_at'] = $now->copy()->addMonths(
                    (int) config('vendor_visit_checklist.badge_validity_months', 12)
                );
            }

            $visit->update($updates);

            return $visit->refresh();
        });
    }

    private function computeOutcome(VendorVisit $visit): VendorVisitStatus
    {
        if ($visit->escalated) {
            return VendorVisitStatus::Submitted;
        }

        if (blank($visit->storefront_photo_path) || blank($visit->owner_photo_path)) {
            return VendorVisitStatus::Failed;
        }

        $hasUnanswered = $visit->items->contains(fn ($item) => $item->passed === null);
        if ($hasUnanswered) {
            return VendorVisitStatus::Failed;
        }

        $criticalFailed = $visit->items
            ->where('criticality', VendorVisitItemCriticality::Critical)
            ->contains(fn ($item) => $item->passed === false);

        return $criticalFailed ? VendorVisitStatus::Failed : VendorVisitStatus::Passed;
    }
}
