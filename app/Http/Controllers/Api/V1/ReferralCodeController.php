<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferralCodeRequest;
use App\Http\Resources\ReferralCodeResource;
use App\Models\ReferralCode;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ReferralCodeController extends Controller
{
    public function __construct(protected ReferralService $referralService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $codes = ReferralCode::query()
            ->where('influencer_id', $request->user()->id)
            ->with('influencer')
            ->withCount('referrals')
            ->latest()
            ->paginate(15);

        return ReferralCodeResource::collection($codes);
    }

    public function store(StoreReferralCodeRequest $request): JsonResponse
    {
        $code = $this->referralService->createReferralCode(
            influencer: $request->user(),
            code: $request->input('code'),
            description: $request->input('description'),
            registrationBonus: $request->input('registration_bonus', 50.00),
            commissionRate: $request->input('commission_rate', 5.00),
            commissionDurationMonths: $request->input('commission_duration_months', 3),
            maxUsages: $request->input('max_usages'),
            expiresAt: $request->input('expires_at') ? new \DateTime($request->input('expires_at')) : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Referral code created successfully.',
            'data' => new ReferralCodeResource($code->load('influencer')),
        ], 201);
    }

    public function show(ReferralCode $referralCode): JsonResponse
    {
        $this->authorize('view', $referralCode);

        return response()->json([
            'success' => true,
            'data' => new ReferralCodeResource($referralCode->load(['influencer', 'referrals'])),
        ]);
    }

    public function update(Request $request, ReferralCode $referralCode): JsonResponse
    {
        $this->authorize('update', $referralCode);

        $validated = $request->validate([
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
            'max_usages' => ['nullable', 'integer', 'min:'.$referralCode->usage_count],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $referralCode->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Referral code updated successfully.',
            'data' => new ReferralCodeResource($referralCode->fresh('influencer')),
        ]);
    }

    public function destroy(ReferralCode $referralCode): JsonResponse
    {
        $this->authorize('delete', $referralCode);

        if ($referralCode->usage_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a referral code that has been used.',
            ], 422);
        }

        $referralCode->delete();

        return response()->json([
            'success' => true,
            'message' => 'Referral code deleted successfully.',
        ]);
    }
}
