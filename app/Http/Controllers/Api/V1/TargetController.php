<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTargetRequest;
use App\Http\Resources\TargetResource;
use App\Models\Target;
use App\Models\User;
use App\Services\TargetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TargetController extends Controller
{
    public function __construct(protected TargetService $targetService) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Target::class);

        $targets = Target::query()
            ->with(['user', 'assignedBy'])
            ->when($request->input('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($request->input('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->input('type'), fn ($q, $type) => $q->where('target_type', $type))
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

    public function store(StoreTargetRequest $request): JsonResponse
    {
        $user = User::findOrFail($request->input('user_id'));

        try {
            $target = $this->targetService->createTarget(
                $user,
                $request->input('target_type'),
                $request->input('target_value'),
                $request->input('base_bonus'),
                $request->input('overachievement_bonus'),
                $request->input('start_date'),
                $request->input('end_date'),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Target assigned successfully.',
                'data' => new TargetResource($target->load(['user', 'assignedBy'])),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function show(Target $target): JsonResponse
    {
        $this->authorize('view', $target);

        return response()->json([
            'success' => true,
            'data' => new TargetResource($target->load(['user', 'assignedBy'])),
        ]);
    }

    public function update(Request $request, Target $target): JsonResponse
    {
        $this->authorize('update', $target);

        $request->validate([
            'target_value' => 'sometimes|numeric|min:1',
            'base_bonus' => 'sometimes|numeric|min:0',
            'overachievement_bonus' => 'sometimes|numeric|min:0',
            'end_date' => 'sometimes|date|after:start_date',
        ]);

        if ($target->status !== Target::STATUS_ACTIVE) {
            return response()->json([
                'success' => false,
                'message' => 'Only active targets can be updated.',
            ], 422);
        }

        $target->update($request->only([
            'target_value',
            'base_bonus',
            'overachievement_bonus',
            'end_date',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Target updated successfully.',
            'data' => new TargetResource($target->fresh()),
        ]);
    }

    public function destroy(Target $target): JsonResponse
    {
        $this->authorize('delete', $target);

        if ($target->status === Target::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a completed target.',
            ], 422);
        }

        $target->delete();

        return response()->json([
            'success' => true,
            'message' => 'Target deleted successfully.',
        ]);
    }
}
