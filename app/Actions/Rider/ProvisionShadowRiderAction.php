<?php

namespace App\Actions\Rider;

use App\Models\Rider;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ProvisionShadowRiderAction
{
    /**
     * Idempotently provision (or fetch) a shadow Rider record for the given admin User.
     *
     * Keyed on user_id, so repeat calls return the same Rider -- preserving any
     * delivery/earnings history accrued during dev testing.
     */
    public function __invoke(User $admin): Rider
    {
        return Rider::firstOrCreate(
            ['user_id' => $admin->id],
            [
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone ?? "admin-{$admin->id}",
                'password' => Hash::make(Str::random(40)),
                'vehicle_category' => 'motorbike',
                'status' => 'approved',
                'is_active' => true,
            ]
        );
    }
}
