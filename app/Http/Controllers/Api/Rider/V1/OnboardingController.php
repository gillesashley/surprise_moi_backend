<?php

namespace App\Http\Controllers\Api\Rider\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Rider\V1\SubmitDocumentsRequest;
use App\Http\Resources\Api\Rider\V1\RiderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /**
     * Submit documents for rider verification.
     */
    public function submitDocuments(SubmitDocumentsRequest $request): JsonResponse
    {
        $rider = $request->user('rider');

        if ($rider->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is already approved.',
            ], 403);
        }

        $data = [
            'vehicle_type' => $request->vehicle_type,
            'license_plate' => $request->license_plate,
            'status' => 'under_review',
        ];

        foreach (['ghana_card_front', 'ghana_card_back', 'drivers_license', 'vehicle_photo'] as $doc) {
            if ($request->hasFile($doc)) {
                $data[$doc] = $request->file($doc)->store("riders/{$rider->id}/{$doc}", 's3');
            }
        }

        $rider->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Documents submitted for review.',
            'data' => new RiderResource($rider->fresh()),
        ]);
    }

    /**
     * Check the rider's onboarding status.
     */
    public function status(Request $request): JsonResponse
    {
        $rider = $request->user('rider');

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $rider->status,
                'has_documents' => (bool) $rider->ghana_card_front,
                'rider' => new RiderResource($rider),
            ],
        ]);
    }

    /**
     * Resubmit documents after rejection.
     */
    public function resubmitDocuments(SubmitDocumentsRequest $request): JsonResponse
    {
        $rider = $request->user('rider');

        if (! $rider->isRejected()) {
            return response()->json([
                'success' => false,
                'message' => 'You can only resubmit documents after rejection.',
            ], 403);
        }

        return $this->submitDocuments($request);
    }
}
