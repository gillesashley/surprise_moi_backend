<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\UpdatePasswordRequest;
use App\Http\Requests\Api\Rider\V1\UpdateProfileRequest;
use App\Http\Requests\Api\Rider\V1\UpdateVehicleRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => new RiderResource($request->user('rider')),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $rider = $request->user('rider');
        $rider->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data' => new RiderResource($rider->fresh()),
        ]);
    }

    public function updateVehicle(UpdateVehicleRequest $request): JsonResponse
    {
        $rider = $request->user('rider');
        $rider->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Vehicle details updated successfully.',
            'data' => new RiderResource($rider->fresh()),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $rider = $request->user('rider');

        if (! Hash::check($request->current_password, $rider->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $rider->update(['password' => $request->password]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully.',
        ]);
    }
}
