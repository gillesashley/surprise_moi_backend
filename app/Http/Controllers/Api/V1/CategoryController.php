<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\CacheService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $type = $request->input('type');
        $cacheKey = 'categories:list:'.($type ?? 'all');

        $categories = Cache::remember($cacheKey, CacheService::TTL_CATEGORIES, function () use ($type) {
            return Category::query()
                ->where('is_active', true)
                ->when($type, function ($query) use ($type) {
                    $query->where('type', $type);
                })
                ->withCount('products')
                ->orderBy('sort_order')
                ->get();
        });

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => CategoryResource::collection($categories),
            ],
        ]);
    }
}
