<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EarningResource;
use App\Http\Resources\PayoutRequestResource;
use App\Http\Resources\ReferralCodeResource;
use App\Http\Resources\ReferralResource;
use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Services\EarningService;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InfluencerDashboardController extends Controller
{
    public function __construct(
        protected ReferralService $referralService,
        protected EarningService $earningService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = $this->referralService->getInfluencerStats($user);
        $earningsSummary = $this->earningService->getUserEarningsSummary($user);

        $recentReferrals = Referral::where('influencer_id', $user->id)
            ->with(['vendor', 'referralCode'])
            ->latest()
            ->limit(5)
            ->get();

        $recentEarnings = Earning::where('user_id', $user->id)
            ->latest('earned_at')
            ->limit(10)
            ->get();

        $referralCodes = ReferralCode::where('influencer_id', $user->id)
            ->withCount('referrals')
            ->latest()
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => array_merge($stats, $earningsSummary),
                'recent_referrals' => ReferralResource::collection($recentReferrals),
                'recent_earnings' => EarningResource::collection($recentEarnings),
                'referral_codes' => ReferralCodeResource::collection($referralCodes),
            ],
        ]);
    }

    public function referrals(Request $request): JsonResponse
    {
        $referrals = Referral::where('influencer_id', $request->user()->id)
            ->with(['vendor', 'referralCode'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => ReferralResource::collection($referrals),
            'meta' => [
                'current_page' => $referrals->currentPage(),
                'last_page' => $referrals->lastPage(),
                'per_page' => $referrals->perPage(),
                'total' => $referrals->total(),
            ],
        ]);
    }

    public function earnings(Request $request): JsonResponse
    {
        $earnings = Earning::where('user_id', $request->user()->id)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('type'), fn ($q, $type) => $q->where('earning_type', $type))
            ->latest('earned_at')
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => EarningResource::collection($earnings),
            'meta' => [
                'current_page' => $earnings->currentPage(),
                'last_page' => $earnings->lastPage(),
                'per_page' => $earnings->perPage(),
                'total' => $earnings->total(),
            ],
        ]);
    }

    public function payouts(Request $request): JsonResponse
    {
        $payouts = PayoutRequest::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => PayoutRequestResource::collection($payouts),
            'meta' => [
                'current_page' => $payouts->currentPage(),
                'last_page' => $payouts->lastPage(),
                'per_page' => $payouts->perPage(),
                'total' => $payouts->total(),
            ],
        ]);
    }
}
