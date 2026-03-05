<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SpecialOffer\StoreSpecialOfferRequest;
use App\Http\Requests\Api\V1\SpecialOffer\UpdateSpecialOfferRequest;
use App\Http\Resources\SpecialOfferResource;
use App\Models\SpecialOffer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class VendorSpecialOfferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $offers = SpecialOffer::query()
            ->forVendor($request->user()->id)
            ->with(['product.shop', 'product.images'])
            ->latest()
            ->paginate(15);

        return SpecialOfferResource::collection($offers);
    }

    public function store(StoreSpecialOfferRequest $request): JsonResponse
    {
        $offer = SpecialOffer::create($request->validated());

        $offer->load(['product.shop', 'product.images']);

        return response()->json([
            'success' => true,
            'message' => 'Special offer created successfully.',
            'data' => new SpecialOfferResource($offer),
        ], 201);
    }

    public function update(UpdateSpecialOfferRequest $request, SpecialOffer $specialOffer): JsonResponse
    {
        $specialOffer->update($request->validated());

        $specialOffer->load(['product.shop', 'product.images']);

        return response()->json([
            'success' => true,
            'message' => 'Special offer updated successfully.',
            'data' => new SpecialOfferResource($specialOffer),
        ]);
    }

    public function destroy(Request $request, SpecialOffer $specialOffer): JsonResponse
    {
        if ($request->user()->cannot('delete', $specialOffer)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $specialOffer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Special offer deleted successfully.',
        ]);
    }
}
