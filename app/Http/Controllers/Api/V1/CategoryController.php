<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->when(request()->filled('type'), function ($query) {
                $query->where('type', request()->input('type'));
            })
            ->withCount('products')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'categories' => CategoryResource::collection($categories),
            ],
        ]);
    }
}
