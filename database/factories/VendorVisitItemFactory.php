<?php

namespace Database\Factories;

use App\Enums\VendorVisitItemCategory;
use App\Enums\VendorVisitItemCriticality;
use App\Models\VendorVisit;
use App\Models\VendorVisitItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorVisitItemFactory extends Factory
{
    protected $model = VendorVisitItem::class;

    public function definition(): array
    {
        return [
            'vendor_visit_id' => VendorVisit::factory(),
            'item_key' => 'identity.person_matches_ghana_card',
            'category' => VendorVisitItemCategory::Identity->value,
            'criticality' => VendorVisitItemCriticality::Critical->value,
            'passed' => null,
            'note' => null,
        ];
    }

    public function critical(): static
    {
        return $this->state(fn () => ['criticality' => VendorVisitItemCriticality::Critical->value]);
    }

    public function informational(): static
    {
        return $this->state(fn () => ['criticality' => VendorVisitItemCriticality::Informational->value]);
    }

    public function passed(): static
    {
        return $this->state(fn () => ['passed' => true]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['passed' => false]);
    }
}
