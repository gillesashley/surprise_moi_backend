<?php

namespace App\Actions\VendorVisit;

use App\Enums\VendorVisitStatus;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorVisit;
use Illuminate\Support\Facades\DB;

class StartVendorVisit
{
    public function execute(
        User $vendor,
        User $agent,
        float $latitude,
        float $longitude,
    ): VendorVisit {
        return DB::transaction(function () use ($vendor, $agent, $latitude, $longitude) {
            $existingDraft = VendorVisit::query()
                ->where('vendor_user_id', $vendor->id)
                ->where('field_agent_user_id', $agent->id)
                ->where('status', VendorVisitStatus::Draft->value)
                ->first();

            if ($existingDraft) {
                return $existingDraft->load('items');
            }

            $visit = VendorVisit::create([
                'vendor_user_id' => $vendor->id,
                'field_agent_user_id' => $agent->id,
                'status' => VendorVisitStatus::Draft->value,
                'started_at' => now(),
                'visit_latitude' => $latitude,
                'visit_longitude' => $longitude,
            ]);

            $this->seedItems($visit, $vendor);

            return $visit->load('items');
        });
    }

    private function seedItems(VendorVisit $visit, User $vendor): void
    {
        $application = VendorApplication::query()
            ->where('user_id', $vendor->id)
            ->latest('id')
            ->first();

        $hasCert = (bool) ($application?->has_business_certificate);
        $hasTin = filled($application?->tin_number);

        foreach (config('vendor_visit_checklist.items', []) as $item) {
            if (! $this->shouldSeed($item['seed_if'], $hasCert, $hasTin)) {
                continue;
            }

            $visit->items()->create([
                'item_key' => $item['key'],
                'category' => $item['category'],
                'criticality' => $item['criticality'],
                'passed' => null,
                'note' => null,
            ]);
        }
    }

    private function shouldSeed(string $seedIf, bool $hasCert, bool $hasTin): bool
    {
        return match ($seedIf) {
            'always' => true,
            'has_business_cert' => $hasCert,
            'has_tin' => $hasTin,
            default => false,
        };
    }
}
