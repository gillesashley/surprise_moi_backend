<?php

namespace App\Policies;

use App\Models\SpecialOffer;
use App\Models\User;

class SpecialOfferPolicy
{
    /**
     * Determine whether the vendor can update the special offer.
     */
    public function update(User $user, SpecialOffer $specialOffer): bool
    {
        return $user->isVendor()
            && $specialOffer->product
            && $specialOffer->product->shop
            && $specialOffer->product->shop->vendor_id === $user->id;
    }

    /**
     * Determine whether the vendor can delete the special offer.
     */
    public function delete(User $user, SpecialOffer $specialOffer): bool
    {
        return $this->update($user, $specialOffer);
    }
}
