<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    public function created(Category $category): void
    {
        $this->clearCaches($category);
    }

    public function updated(Category $category): void
    {
        $this->clearCaches($category);
    }

    public function deleted(Category $category): void
    {
        $this->clearCaches($category);
    }

    private function clearCaches(Category $category): void
    {
        CacheService::flushCategoryCaches();

        // Flush type-specific category caches
        Cache::forget('categories:list:all');
        if ($category->type) {
            Cache::forget("categories:list:{$category->type}");
        }
    }
}
