<?php

namespace App\Policies;

use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function update(User $user, Coupon $coupon): bool
    {
        return $user->isAdmin() || $coupon->vendor_id === $user->id;
    }

    public function delete(User $user, Coupon $coupon): bool
    {
        return $user->isAdmin() || $coupon->vendor_id === $user->id;
    }
}
