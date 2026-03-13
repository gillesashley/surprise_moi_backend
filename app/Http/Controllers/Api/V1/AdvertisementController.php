<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdvertisementResource;
use App\Models\Advertisement;
use App\Services\CacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class AdvertisementController extends Controller
{
    /**
     * Get active advertisements for the app.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $placement = $request->input('placement', '');
        $cacheKey = "advertisements:list:{$placement}";

        $advertisements = Cache::remember($cacheKey, CacheService::TTL_ADVERTISEMENTS, function () use ($placement) {
            return Advertisement::query()
                ->active()
                ->when($placement, fn ($q) => $q->byPlacement($placement))
                ->orderBy('display_order')
                ->orderBy('created_at', 'desc')
                ->get();
        });

        // Batch increment impressions outside cache — always track views
        if ($advertisements->isNotEmpty()) {
            Advertisement::whereIn('id', $advertisements->pluck('id'))->increment('impressions');
        }

        return AdvertisementResource::collection($advertisements);
    }

    /**
     * Show a specific advertisement.
     */
    public function show(Advertisement $advertisement): JsonResponse
    {
        if (! $advertisement->isActive()) {
            return response()->json([
                'message' => 'Advertisement not found or inactive.',
            ], 404);
        }

        $advertisement->incrementImpressions();

        return response()->json([
            'success' => true,
            'data' => new AdvertisementResource($advertisement),
        ]);
    }

    /**
     * Track advertisement click.
     */
    public function trackClick(Advertisement $advertisement): JsonResponse
    {
        $advertisement->incrementClicks();

        return response()->json([
            'success' => true,
            'message' => 'Click tracked successfully.',
        ]);
    }
}
