<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()
            ->with(['category', 'vendor', 'shop', 'images', 'tags'])
            ->where('is_available', true);

        // Filter by category (supports single slug or comma-separated slugs)
        if ($request->filled('category')) {
            $categories = array_map('trim', explode(',', $request->category));
            $query->whereHas('category', function ($q) use ($categories) {
                $q->whereIn('slug', $categories);
            });
        }

        // Filter by category IDs (supports single ID or comma-separated IDs)
        if ($request->filled('category_ids')) {
            $categoryIds = array_map('intval', explode(',', $request->category_ids));
            $query->whereIn('category_id', $categoryIds);
        }

        // Search in name and description (case-insensitive)
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Filter by vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        // Filter by location (vendor's location - bio field)
        if ($request->filled('location')) {
            $location = strtolower($request->location);
            $query->whereHas('vendor', function ($q) use ($location) {
                $q->whereRaw('LOWER(bio) LIKE ?', ["%{$location}%"]);
            });
        }

        // Filter by rating range (supports min and max for ranges like "4.0 - 4.5")
        if ($request->filled('rating_min')) {
            $query->where('rating', '>=', $request->rating_min);
        }

        if ($request->filled('rating_max')) {
            $query->where('rating', '<', $request->rating_max);
        }

        // Filter by colors (supports single color or comma-separated colors)
        if ($request->filled('colors')) {
            $colors = array_map('trim', explode(',', strtolower($request->colors)));
            $query->where(function ($q) use ($colors) {
                foreach ($colors as $color) {
                    // Search in the JSON colors array (case-insensitive, database-agnostic)
                    $q->orWhere('colors', 'LIKE', "%\"{$color}\"%")
                        ->orWhere('colors', 'LIKE', '%"'.ucfirst($color).'"%');
                }
            });
        }

        // Filter by tags/occasions (supports single tag or comma-separated tags)
        if ($request->filled('tags')) {
            $tags = array_map('trim', explode(',', $request->tags));
            $query->whereHas('tags', function ($q) use ($tags) {
                $q->whereIn('slug', $tags);
            });
        }

        // Filter by tag IDs (supports single ID or comma-separated IDs)
        if ($request->filled('tag_ids')) {
            $tagIds = array_map('intval', explode(',', $request->tag_ids));
            $query->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            });
        }

        // Filter featured products only
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Filter by free delivery
        if ($request->boolean('free_delivery')) {
            $query->where('free_delivery', true);
        }

        // Filter by discount (products with active discounts)
        if ($request->boolean('has_discount')) {
            $query->whereNotNull('discount_price')
                ->where('discount_price', '>', 0);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['price', 'rating', 'created_at', 'name', 'discount_percentage'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'products' => ProductResource::collection($products),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                    'from' => $products->firstItem(),
                    'to' => $products->lastItem(),
                ],
                'filters_applied' => $request->only([
                    'category',
                    'category_ids',
                    'search',
                    'min_price',
                    'max_price',
                    'vendor_id',
                    'location',
                    'rating_min',
                    'rating_max',
                    'colors',
                    'tags',
                    'tag_ids',
                    'featured',
                    'free_delivery',
                    'has_discount',
                ]),
            ],
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $product = Product::with([
            'category',
            'vendor',
            'shop',
            'images',
            'variants',
            'tags',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'product' => new ProductDetailResource($product),
            ],
        ]);
    }

    /**
     * Display a product by its share slug.
     */
    public function showBySlug(string $slug)
    {
        $product = Product::with([
            'category',
            'vendor',
            'shop',
            'images',
            'variants',
            'tags',
        ])->where('slug', $slug)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => [
                'product' => new ProductDetailResource($product),
            ],
        ]);
    }

    /**
     * Store a newly created product.
     */
    public function store(\App\Http\Requests\Api\V1\Product\StoreProductRequest $request)
    {
        $data = $request->validated();
        $data['vendor_id'] = $request->user()->id;

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails');
            $data['thumbnail'] = $thumbnailPath;
        }

        $product = Product::create($data);

        // Handle multiple product images
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $index => $image) {
                $imagePath = $image->store('products/images');
                $product->images()->create([
                    'image_path' => $imagePath,
                    'sort_order' => $index,
                    'is_primary' => $index === 0,
                ]);
            }
        }

        // Attach tags if provided
        if (! empty($data['tag_ids'])) {
            $product->tags()->attach($data['tag_ids']);
        }

        $product->load(['category', 'vendor', 'shop', 'images', 'tags']);

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully.',
            'data' => [
                'product' => new ProductDetailResource($product),
            ],
        ], 201);
    }

    /**
     * Update the specified product.
     */
    public function update(\App\Http\Requests\Api\V1\Product\UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if it exists
            if ($product->thumbnail) {
                Storage::disk()->delete($product->thumbnail);
            }

            $thumbnailPath = $request->file('thumbnail')->store('products/thumbnails');
            $data['thumbnail'] = $thumbnailPath;
        }

        $product->update($data);

        // Handle image removal
        if (! empty($data['remove_images'])) {
            $imagesToRemove = $product->images()->whereIn('id', $data['remove_images'])->get();
            foreach ($imagesToRemove as $image) {
                Storage::disk()->delete($image->image_path);
                $image->delete();
            }
        }

        // Handle new images
        if ($request->hasFile('images')) {
            $currentMaxOrder = $product->images()->max('sort_order') ?? -1;
            foreach ($request->file('images') as $index => $image) {
                $imagePath = $image->store('products/images');
                $product->images()->create([
                    'image_path' => $imagePath,
                    'sort_order' => $currentMaxOrder + $index + 1,
                    'is_primary' => false,
                ]);
            }
        }

        // Sync tags if provided
        if (isset($data['tag_ids'])) {
            $product->tags()->sync($data['tag_ids']);
        }

        $product->load(['category', 'vendor', 'shop', 'images', 'tags']);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully.',
            'data' => [
                'product' => new ProductDetailResource($product),
            ],
        ]);
    }

    /**
     * Remove the specified product.
     */
    public function destroy(Product $product)
    {
        // Check authorization
        if ($product->vendor_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this product.',
            ], 403);
        }

        // Delete associated images from storage
        if ($product->thumbnail) {
            Storage::disk()->delete($product->thumbnail);
        }

        foreach ($product->images as $image) {
            Storage::disk()->delete($image->image_path);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully.',
        ]);
    }
}
