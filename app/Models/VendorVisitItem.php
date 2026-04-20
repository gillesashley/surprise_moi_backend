<?php

namespace App\Models;

use App\Enums\VendorVisitItemCategory;
use App\Enums\VendorVisitItemCriticality;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorVisitItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_visit_id',
        'item_key',
        'category',
        'criticality',
        'passed',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'category' => VendorVisitItemCategory::class,
            'criticality' => VendorVisitItemCriticality::class,
            'passed' => 'boolean',
        ];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(VendorVisit::class, 'vendor_visit_id');
    }

    public function isAnswered(): bool
    {
        return $this->passed !== null;
    }
}
