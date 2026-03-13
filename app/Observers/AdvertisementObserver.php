<?php

namespace App\Observers;

use App\Models\Advertisement;
use App\Services\CacheService;

class AdvertisementObserver
{
    public function created(Advertisement $advertisement): void
    {
        CacheService::flushAdvertisementCaches();
    }

    public function updated(Advertisement $advertisement): void
    {
        CacheService::flushAdvertisementCaches();
    }

    public function deleted(Advertisement $advertisement): void
    {
        CacheService::flushAdvertisementCaches();
    }
}
