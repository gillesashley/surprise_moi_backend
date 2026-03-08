<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeviceToken\DeleteDeviceTokenRequest;
use App\Http\Requests\Api\V1\DeviceToken\StoreDeviceTokenRequest;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;

class DeviceTokenController extends Controller
{
    public function store(StoreDeviceTokenRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Check if token already exists (for any user)
        $existing = DeviceToken::where('token', $validated['token'])->first();

        if ($existing) {
            // Reassign to current user if different, and update metadata
            $existing->update([
                'user_id' => $user->id,
                'device_name' => $validated['device_name'] ?? $existing->device_name,
                'platform' => $validated['platform'] ?? $existing->platform,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device token updated',
            ]);
        }

        DeviceToken::create([
            'user_id' => $user->id,
            'token' => $validated['token'],
            'device_name' => $validated['device_name'] ?? null,
            'platform' => $validated['platform'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Device token registered',
        ], 201);
    }

    public function destroy(DeleteDeviceTokenRequest $request): JsonResponse
    {
        $request->user()
            ->deviceTokens()
            ->where('token', $request->validated('token'))
            ->delete();

        return response()->json([
            'success' => true,
            'message' => 'Device token removed',
        ]);
    }
}
