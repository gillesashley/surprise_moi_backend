<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReviewPolicy
{
    public function update(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function delete(User $user, Review $review): bool
    {
        return $review->user_id === $user->id;
    }

    public function viewVendorReviews(User $user): bool
    {
        return $user->role === 'vendor';
    }

    public function reply(User $user, Review $review): bool
    {
        if ($user->role !== 'vendor') {
            return false;
        }

        $itemType = $review->item_type;
        $itemId = $review->item_id;

        if (! $itemType || ! $itemId) {
            return false;
        }

        $modelClass = match ($itemType) {
            'product' => Product::class,
            'service' => Service::class,
            default => null,
        };

        if (! $modelClass) {
            return false;
        }

        $query = $modelClass::query();
        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->withTrashed();
        }

        return $query->whereKey($itemId)
            ->where('vendor_id', $user->id)
            ->exists();
    }
}
