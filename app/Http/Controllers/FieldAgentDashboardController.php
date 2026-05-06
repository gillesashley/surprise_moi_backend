<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\ReferralCode;
use App\Models\Target;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\EarningService;
use App\Services\TargetService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FieldAgentDashboardController extends Controller
{
    public function __construct(
        protected TargetService $targetService,
        protected EarningService $earningService
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $period = $this->resolvePeriod($request);
        $isMember = $user->parent_user_id !== null;

        $referralCode = $isMember ? null : $this->getOrCreateReferralCode($user);
        $earningsSummary = $isMember ? null : $this->earningService->getUserEarningsSummary($user);
        $referralStats = $isMember ? ['total_earned' => 0] : app(\App\Services\ReferralService::class)->getInfluencerStats($user);

        return Inertia::render('field-agent/dashboard', [
            'agent' => [
                'id' => $user->id,
                'first_name' => $user->first_name ?? (explode(' ', (string) $user->name)[0] ?: $user->name),
                'referral_points' => $isMember ? 0 : (int) ($user->referral_points ?? 0),
                'earned_amount' => (float) ($referralStats['total_earned'] ?? 0),
            ],
            'isMember' => $isMember,
            'period' => $period,
            'referralCode' => $referralCode ? ['code' => $referralCode->code] : null,
            'vendorStats' => $this->computeVendorStats($user, $period),
            'earningsSummary' => $earningsSummary,
            'activeTarget' => $isMember ? null : $this->computeActiveTarget($user),
            'recentVendors' => $this->computeRecentVendors($user),
        ]);
    }

    public function targets(Request $request): Response
    {
        $targets = Target::where('user_id', $request->user()->id)
            ->with(['assignedBy'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return Inertia::render('field-agent/targets', [
            'targets' => $targets,
        ]);
    }

    public function earnings(Request $request): Response
    {
        $earnings = Earning::where('user_id', $request->user()->id)
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('type'), fn ($q, $type) => $q->where('earning_type', $type))
            ->latest('earned_at')
            ->paginate(15);

        $referralStats = app(\App\Services\ReferralService::class)->getInfluencerStats($request->user());

        return Inertia::render('field-agent/earnings', [
            'earnings' => $earnings,
            'referral_points' => (int) ($request->user()->referral_points ?? 0),
            'total_earned_amount' => (float) ($referralStats['total_earned'] ?? 0),
        ]);
    }

    public function payouts(Request $request): Response
    {
        $payoutRequests = PayoutRequest::where('user_id', $request->user()->id)
            ->with('user')
            ->latest()
            ->paginate(15);

        return Inertia::render('field-agent/payouts', [
            'payoutRequests' => $payoutRequests,
        ]);
    }

    private function getOrCreateReferralCode(User $agent): ReferralCode
    {
        $code = ReferralCode::where('influencer_id', $agent->id)->first();

        if ($code) {
            return $code;
        }

        $code = new ReferralCode(['influencer_id' => $agent->id, 'is_active' => true]);
        $code->prefix = ReferralCode::getPrefixForRole('field_agent');
        $code->save();

        return $code;
    }

    private function resolvePeriod(Request $request): string
    {
        $raw = (string) $request->input('period', 'week');

        return in_array($raw, ['today', 'week', 'month'], true) ? $raw : 'week';
    }

    /**
     * @return array{total:int, pending:int, approved:int, rejected:int}
     */
    private function computeVendorStats(User $agent, string $period): array
    {
        $now = CarbonImmutable::now();
        $start = match ($period) {
            'today' => $now->startOfDay(),
            'month' => $now->startOfMonth(),
            default => $now->startOfWeek(),
        };

        $base = VendorApplication::query();
        if ($agent->parent_user_id !== null) {
            $base->where('onboarded_by_user_id', $agent->id);
        } else {
            $base->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $agent->id));
        }

        $total = (clone $base)->count();
        $inPeriod = (clone $base)->where('created_at', '>=', $start);

        return [
            'total' => $total,
            'pending' => (clone $inPeriod)
                ->whereIn('status', [VendorApplication::STATUS_PENDING, VendorApplication::STATUS_UNDER_REVIEW])
                ->count(),
            'approved' => (clone $inPeriod)->where('status', VendorApplication::STATUS_APPROVED)->count(),
            'rejected' => (clone $inPeriod)->where('status', VendorApplication::STATUS_REJECTED)->count(),
        ];
    }

    /**
     * @return array<int, array{id:int, business_name:string, status:string, created_at:string|null}>
     */
    private function computeRecentVendors(User $agent): array
    {
        $query = VendorApplication::query();
        if ($agent->parent_user_id !== null) {
            $query->where('onboarded_by_user_id', $agent->id);
        } else {
            $query->whereHas('referralCode', fn ($q) => $q->where('influencer_id', $agent->id));
        }

        return $query
            ->with('user:id,name,business_name')
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (VendorApplication $app) => [
                'id' => $app->id,
                'business_name' => $app->user?->business_name ?: ($app->user?->name ?? ''),
                'status' => $app->status,
                'created_at' => $app->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function computeActiveTarget(User $agent): ?array
    {
        $target = Target::where('user_id', $agent->id)
            ->where('status', Target::STATUS_ACTIVE)
            ->latest()
            ->first();

        if (! $target) {
            return null;
        }

        return [
            'id' => $target->id,
            'current' => (float) $target->current_value,
            'goal' => (float) $target->target_value,
            'completion_percentage' => $target->getCompletionPercentage(),
            'ends_at' => $target->end_date?->toIso8601String(),
        ];
    }
}
