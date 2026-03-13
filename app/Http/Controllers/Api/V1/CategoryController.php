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
        $type = $request->input('type', 'all');
        $cacheKey = "categories:list:{$type}";

        $categories = Cache::remember($cacheKey, CacheService::TTL_CATEGORIES, function () use ($request) {
            return Category::query()
                ->where('is_active', true)
                ->when($request->filled('type'), function ($query) use ($request) {
                    $query->where('type', $request->input('type'));
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
