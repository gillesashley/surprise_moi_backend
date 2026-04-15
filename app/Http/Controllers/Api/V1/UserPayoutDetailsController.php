<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserPayoutDetailsRequest;
use App\Http\Requests\VerifyPayoutDetailsRequest;
use App\Http\Resources\UserPayoutDetailResource;
use App\Models\UserPayoutDetail;
use App\Services\PaystackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPayoutDetailsController extends Controller
{
    public function __construct(private readonly PaystackService $paystack) {}

    public function show(Request $request): JsonResponse
    {
        $detail = $request->user()->defaultUserPayoutDetail();

        return response()->json([
            'data' => $detail ? new UserPayoutDetailResource($detail) : null,
        ]);
    }

    public function store(StoreUserPayoutDetailsRequest $request): JsonResponse
    {
        $user = $request->user();
        $number = $request->validated('mobile_money_number');
        $provider = $request->validated('mobile_money_provider');

        try {
            $result = $this->paystack->createMobileMoneyRecipient(
                mobileNumber: $number,
                provider: $provider,
                name: $user->name ?: 'Referral User',
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        $detail = DB::transaction(function () use ($user, $number, $provider, $result) {
            // Demote existing defaults so there is only one is_default=true per user.
            $user->userPayoutDetails()->update(['is_default' => false]);

            return UserPayoutDetail::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'mobile_money_number' => $number,
                    'mobile_money_provider' => $provider,
                ],
                [
                    'payout_method' => 'mobile_money',
                    'account_name' => $result['account_name'],
                    'paystack_recipient_code' => $result['recipient_code'],
                    'is_verified' => true,
                    'is_default' => true,
                ]
            );
        });

        return response()->json([
            'data' => new UserPayoutDetailResource($detail),
        ], 201);
    }

    public function verify(VerifyPayoutDetailsRequest $request): JsonResponse
    {
        $result = $this->paystack->resolveMobileMoneyAccount(
            mobileNumber: $request->validated('mobile_money_number'),
            provider: $request->validated('mobile_money_provider'),
        );

        return response()->json([
            'data' => [
                'valid' => (bool) $result['valid'],
                'account_name' => $result['account_name'],
            ],
        ]);
    }
}
