<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Services\EarningService;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InfluencerDashboardController extends Controller
{
    public function __construct(
        protected ReferralService $referralService,
        protected EarningService $earningService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get stats
        $stats = $this->referralService->getInfluencerStats($user);
        $earningsSummary = $this->earningService->getUserEarningsSummary($user);

        // Get recent referrals
        $recentReferrals = Referral::where('influencer_id', $user->id)
            ->with(['vendor', 'referralCode'])
            ->latest()
            ->limit(5)
            ->get();

        // Get recent earnings
        $recentEarnings = Earning::where('user_id', $user->id)
            ->latest('earned_at')
            ->limit(5)
            ->get();

        // Get referral codes
        $referralCodes = ReferralCode::where('influencer_id', $user->id)
            ->withCount('referrals')
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('influencer/dashboard', [
            'stats' => array_merge($stats ?? [], $earningsSummary ?? []),
            'recent_referrals' => $recentReferrals ?? [],
            'recent_earnings' => $recentEarnings ?? [],
            'referral_codes' => $referralCodes ?? [],
        ]);
    }

    public function referrals(Request $request): Response
    {
        $referrals = Referral::where('influencer_id', $request->user()->id)
            ->with(['vendor', 'referralCode'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return Inertia::render('influencer/referrals', [
            'referrals' => $referrals,
        ]);
    }

    public function earnings(Request $request): Response
    {
        $earnings = Earning::where('user_id', $request->user()->id)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('type'), fn ($q, $type) => $q->where('earning_type', $type))
            ->latest('earned_at')
            ->paginate(15);

        return Inertia::render('influencer/earnings', [
            'earnings' => $earnings,
        ]);
    }

    public function payouts(Request $request): Response
    {
        $payoutRequests = PayoutRequest::where('user_id', $request->user()->id)
            ->with('user')
            ->latest()
            ->paginate(15);

        return Inertia::render('influencer/payouts', [
            'payoutRequests' => $payoutRequests,
        ]);
    }
}
