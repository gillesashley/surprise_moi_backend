<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialOfferResource;
use App\Models\SpecialOffer;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;

class PublicSpecialOfferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $page = $request->input('page', 1);
        $cacheKey = "special_offers:page:{$page}";

        $offers = Cache::remember($cacheKey, CacheService::TTL_SPECIAL_OFFERS, function () {
            return SpecialOffer::query()
                ->current()
                ->with(['product.shop', 'product.images'])
                ->latest()
                ->paginate(15);
        });

        return SpecialOfferResource::collection($offers);
    }
}
