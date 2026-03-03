<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PartnerProfile\StorePartnerProfileRequest;
use App\Http\Requests\Api\V1\PartnerProfile\UpdatePartnerProfileRequest;
use App\Models\PartnerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerProfileController extends Controller
{
    /**
     * List the authenticated user's partner profiles.
     */
    public function index(Request $request): JsonResponse
    {
        $profiles = $request->user()
            ->partnerProfiles()
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($profiles);
    }

    /**
     * Create a new partner profile.
     */
    public function store(StorePartnerProfileRequest $request): JsonResponse
    {
        $profile = $request->user()->partnerProfiles()->create(
            $request->validated()
        );

        return response()->json([
            'message' => 'Partner profile created successfully.',
            'data' => $profile,
        ], 201);
    }

    /**
     * Show a specific partner profile.
     */
    public function show(Request $request, PartnerProfile $partnerProfile): JsonResponse
    {
        if ($partnerProfile->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return response()->json(['data' => $partnerProfile]);
    }

    /**
     * Update a partner profile.
     */
    public function update(UpdatePartnerProfileRequest $request, PartnerProfile $partnerProfile): JsonResponse
    {
        if ($partnerProfile->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $partnerProfile->update($request->validated());

        return response()->json([
            'message' => 'Partner profile updated successfully.',
            'data' => $partnerProfile->fresh(),
        ]);
    }

    /**
     * Delete a partner profile.
     */
    public function destroy(Request $request, PartnerProfile $partnerProfile): JsonResponse
    {
        if ($partnerProfile->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $partnerProfile->delete();

        return response()->json(['message' => 'Partner profile deleted successfully.']);
    }
}
