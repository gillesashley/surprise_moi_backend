<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferralPayoutRequest;
use App\Http\Resources\PayoutRequestResource;
use App\Models\PayoutRequest;
use App\Models\UserPayoutDetail;
use App\Services\ReferralPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyReferralPayoutController extends Controller
{
    public function __construct(private readonly ReferralPayoutService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = PayoutRequest::query()
            ->where('user_id', $request->user()->id)
            ->where('source', PayoutRequest::SOURCE_REFERRAL_MILESTONE)
            ->with('userPayoutDetail')
            ->latest();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $paginated = $query->paginate((int) $request->query('per_page', 20));

        return response()->json([
            'data' => PayoutRequestResource::collection($paginated)->response()->getData(true),
        ]);
    }

    public function show(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        abort_unless($payoutRequest->user_id === $request->user()->id, 403);
        abort_unless($payoutRequest->source === PayoutRequest::SOURCE_REFERRAL_MILESTONE, 404);

        $payoutRequest->load('userPayoutDetail');

        return response()->json([
            'data' => new PayoutRequestResource($payoutRequest),
        ]);
    }

    public function store(StoreReferralPayoutRequest $request): JsonResponse
    {
        $user = $request->user();
        $detailId = $request->validated('payout_detail_id');

        $detail = $detailId
            ? UserPayoutDetail::where('user_id', $user->id)->where('id', $detailId)->first()
            : $user->defaultUserPayoutDetail();

        if (! $detail) {
            return response()->json([
                'message' => 'Please save your mobile money details before requesting a payout.',
            ], 422);
        }

        try {
            $payout = $this->service->create($user, $detail);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $payout->load('userPayoutDetail');

        return response()->json([
            'data' => new PayoutRequestResource($payout),
        ], 201);
    }

    public function cancel(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        abort_unless($payoutRequest->user_id === $request->user()->id, 403);
        abort_unless($payoutRequest->source === PayoutRequest::SOURCE_REFERRAL_MILESTONE, 404);

        if ($payoutRequest->status !== PayoutRequest::STATUS_PENDING) {
            return response()->json([
                'message' => 'Only pending requests can be cancelled.',
            ], 409);
        }

        $payoutRequest->update([
            'status' => PayoutRequest::STATUS_CANCELLED,
        ]);

        $payoutRequest->load('userPayoutDetail');

        return response()->json([
            'data' => new PayoutRequestResource($payoutRequest),
        ]);
    }
}
