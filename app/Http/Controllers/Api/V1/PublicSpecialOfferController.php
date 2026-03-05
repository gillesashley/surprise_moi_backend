<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialOfferResource;
use App\Models\SpecialOffer;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicSpecialOfferController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $offers = SpecialOffer::query()
            ->current()
            ->with(['product.shop', 'product.images'])
            ->latest()
            ->paginate(15);

        return SpecialOfferResource::collection($offers);
    }
}
