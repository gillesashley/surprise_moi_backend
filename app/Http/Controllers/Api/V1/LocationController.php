<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Location\AutocompleteRequest;
use App\Http\Requests\Api\V1\Location\GeocodeRequest;
use App\Http\Requests\Api\V1\Location\PlaceDetailsRequest;
use App\Services\GoogleMapsService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    protected GoogleMapsService $googleMapsService;

    public function __construct(GoogleMapsService $googleMapsService)
    {
        $this->googleMapsService = $googleMapsService;
    }

    /**
     * Autocomplete places based on search query.
     *
     * This endpoint provides place suggestions as the user types.
     * Useful for location search/autocomplete features.
     *
     *
     * @example GET /api/v1/locations/autocomplete?query=Golden Avenue&country=GH
     */
    public function autocomplete(AutocompleteRequest $request): JsonResponse
    {
        $result = $this->googleMapsService->autocomplete(
            query: $request->input('query'),
            country: $request->input('country'),
            language: $request->input('language', 'en')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    /**
     * Get detailed information about a place by place ID.
     *
     *
     * @example GET /api/v1/locations/place-details?place_id=ChIJN1t_tDeuEmsRUsoyG83frY4
     */
    public function placeDetails(PlaceDetailsRequest $request): JsonResponse
    {
        $result = $this->googleMapsService->getPlaceDetails(
            placeId: $request->input('place_id'),
            language: $request->input('language', 'en')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }

    /**
     * Reverse geocode coordinates to address.
     *
     * This endpoint converts latitude and longitude coordinates to a human-readable address.
     * Useful for "Use my current location" features.
     *
     *
     * @example GET /api/v1/locations/geocode?latitude=5.6037&longitude=-0.1870
     */
    public function geocode(GeocodeRequest $request): JsonResponse
    {
        $result = $this->googleMapsService->reverseGeocode(
            latitude: (float) $request->input('latitude'),
            longitude: (float) $request->input('longitude'),
            language: $request->input('language', 'en')
        );

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'],
        ]);
    }
}
