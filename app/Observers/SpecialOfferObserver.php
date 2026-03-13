<?php

namespace App\Observers;

use App\Models\SpecialOffer;
use App\Services\CacheService;

class SpecialOfferObserver
{
    public function created(SpecialOffer $specialOffer): void
    {
        CacheService::flushSpecialOfferCaches();
        CacheService::flushProductCaches();
    }

    public function updated(SpecialOffer $specialOffer): void
    {
        CacheService::flushSpecialOfferCaches();
        CacheService::flushProductCaches();
    }

    public function deleted(SpecialOffer $specialOffer): void
    {
        CacheService::flushSpecialOfferCaches();
        CacheService::flushProductCaches();
    }
}
