<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EarningResource;
use App\Http\Resources\TargetResource;
use App\Models\Earning;
use App\Models\Target;
use App\Services\EarningService;
use App\Services\TargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MarketerDashboardController extends Controller
{
    public function __construct(
        protected TargetService $targetService,
        protected EarningService $earningService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $targetStats = $this->targetService->getUserTargetStats($user);
        $earningsSummary = $this->earningService->getUserEarningsSummary($user);

        $currentQuarter = ceil(now()->month / 3);
        $currentYear = now()->year;
        $quarterlyEarnings = $this->earningService->getQuarterlyEarnings($user, $currentYear, $currentQuarter);

        $activeTargets = Target::where('user_id', $user->id)
            ->where('status', Target::STATUS_ACTIVE)
            ->with(['assignedBy'])
            ->latest()
            ->get();

        $recentEarnings = Earning::where('user_id', $user->id)
            ->where('earning_type', Earning::TYPE_SIGN_ON_BONUS)
            ->latest('earned_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => array_merge($targetStats, $earningsSummary, [
                    'current_quarter' => $currentQuarter,
                    'current_year' => $currentYear,
                    'quarterly_earnings' => $quarterlyEarnings,
                ]),
                'active_targets' => TargetResource::collection($activeTargets),
                'recent_sign_on_bonuses' => EarningResource::collection($recentEarnings),
            ],
        ]);
    }

    public function quarterlyEarnings(Request $request): JsonResponse
    {
        $year = $request->input('year', now()->year);
        $quarter = $request->input('quarter', ceil(now()->month / 3));

        $earnings = $this->earningService->getQuarterlyEarnings($request->user(), $year, $quarter);

        return response()->json([
            'success' => true,
            'data' => [
                'year' => $year,
                'quarter' => $quarter,
                'total_earnings' => $earnings,
            ],
        ]);
    }

    public function targets(Request $request): JsonResponse
    {
        $targets = Target::where('user_id', $request->user()->id)
            ->with(['assignedBy'])
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15);

        return response()->json([
            'success' => true,
            'data' => TargetResource::collection($targets),
            'meta' => [
                'current_page' => $targets->currentPage(),
                'last_page' => $targets->lastPage(),
                'per_page' => $targets->perPage(),
                'total' => $targets->total(),
            ],
        ]);
    }
}
