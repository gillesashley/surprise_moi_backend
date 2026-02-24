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

class FieldAgentDashboardController extends Controller
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

        $activeTargets = Target::where('user_id', $user->id)
            ->where('status', Target::STATUS_ACTIVE)
            ->with(['assignedBy'])
            ->latest()
            ->get();

        $recentEarnings = Earning::where('user_id', $user->id)
            ->latest('earned_at')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => array_merge($targetStats, $earningsSummary),
                'active_targets' => TargetResource::collection($activeTargets),
                'recent_earnings' => EarningResource::collection($recentEarnings),
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
}
