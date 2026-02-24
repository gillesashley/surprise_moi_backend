<?php

namespace App\Http\Controllers;

use App\Models\Earning;
use App\Models\PayoutRequest;
use App\Models\Target;
use App\Services\EarningService;
use App\Services\TargetService;
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

        // Get stats
        $targetStats = $this->targetService->getUserTargetStats($user);
        $earningsSummary = $this->earningService->getUserEarningsSummary($user);

        // Get active targets
        $activeTargets = Target::where('user_id', $user->id)
            ->where('status', Target::STATUS_ACTIVE)
            ->with(['assignedBy'])
            ->latest()
            ->get();

        // Get recent earnings
        $recentEarnings = Earning::where('user_id', $user->id)
            ->latest('earned_at')
            ->limit(5)
            ->get();

        return Inertia::render('field-agent/dashboard', [
            'stats' => array_merge($targetStats ?? [], $earningsSummary ?? []),
            'activeTargets' => $activeTargets ?? [],
            'recentEarnings' => $recentEarnings ?? [],
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

        return Inertia::render('field-agent/earnings', [
            'earnings' => $earnings,
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
}
