<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Shop\StoreShopRequest;
use App\Http\Requests\Api\V1\Shop\UpdateShopRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\ShopResource;
use App\Models\Shop;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Display a listing of shops (public - all active shops).
     */
    public function index(Request $request)
    {
        $query = Shop::query()
            ->with(['vendor', 'category'])
            ->withCount(['products', 'services'])
            ->where('is_active', true);

        // Filter by vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        // Search in name, description, and location
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(location) LIKE ?', ["%{$search}%"]);
            });
        }

        // Filter by location
        if ($request->filled('location')) {
            $location = strtolower($request->location);
            $query->whereRaw('LOWER(location) LIKE ?', ["%{$location}%"]);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['created_at', 'name', 'products_count', 'services_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $shops = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'shops' => ShopResource::collection($shops),
                'pagination' => [
                    'current_page' => $shops->currentPage(),
                    'per_page' => $shops->perPage(),
                    'total' => $shops->total(),
                    'last_page' => $shops->lastPage(),
                    'from' => $shops->firstItem(),
                    'to' => $shops->lastItem(),
                ],
            ],
        ]);
    }

    /**
     * Get all shops for the authenticated vendor.
     */
    public function myShops(Request $request)
    {
        $shops = Shop::query()
            ->where('vendor_id', $request->user()->id)
            ->with(['category'])
            ->withCount(['products', 'services'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'shops' => ShopResource::collection($shops),
            ],
        ]);
    }

    /**
     * Display the specified shop.
     */
    public function show(string $id)
    {
        $shop = Shop::with(['vendor', 'category'])
            ->withCount(['products', 'services'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'shop' => new ShopResource($shop),
            ],
        ]);
    }

    /**
     * Store a newly created shop.
     */
    public function store(StoreShopRequest $request)
    {
        $data = $request->validated();
        $data['vendor_id'] = $request->user()->id;

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('shops/logos', 'public');
            $data['logo'] = $logoPath;
        }

        $shop = Shop::create($data);
        $shop->load('vendor', 'category');

        return response()->json([
            'success' => true,
            'message' => 'Shop created successfully.',
            'data' => [
                'shop' => new ShopResource($shop),
            ],
        ], 201);
    }

    /**
     * Update the specified shop.
     */
    public function update(UpdateShopRequest $request, Shop $shop)
    {
        $data = $request->validated();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if it exists
            if ($shop->logo && file_exists(public_path($shop->logo))) {
                unlink(public_path($shop->logo));
            }

            $logoPath = $request->file('logo')->store('shops/logos', 'public');
            $data['logo'] = $logoPath;
        }

        $shop->update($data);
        $shop->load('vendor', 'category');

        return response()->json([
            'success' => true,
            'message' => 'Shop updated successfully.',
            'data' => [
                'shop' => new ShopResource($shop),
            ],
        ]);
    }

    /**
     * Remove the specified shop.
     */
    public function destroy(Shop $shop)
    {
        // Check if user owns the shop
        if ($shop->vendor_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to delete this shop.',
            ], 403);
        }

        $shop->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shop deleted successfully.',
        ]);
    }

    /**
     * Get all products for a specific shop.
     */
    public function products(Request $request, string $id)
    {
        $shop = Shop::findOrFail($id);

        $query = $shop->products()
            ->with(['category', 'vendor', 'images', 'tags'])
            ->where('is_available', true);

        // Apply standard product filters
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['price', 'rating', 'created_at', 'name'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min($request->input('per_page', 20), 100);
        $products = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'shop' => new ShopResource($shop),
                'products' => ProductResource::collection($products),
                'pagination' => [
                    'current_page' => $products->currentPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                    'last_page' => $products->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Get all services for a specific shop.
     */
    public function services(Request $request, string $id)
    {
        $shop = Shop::findOrFail($id);

        $query = $shop->services()
            ->with(['vendor'])
            ->where('availability', 'available');

        // Apply standard service filters
        if ($request->filled('search')) {
            $search = strtolower($request->search);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(description) LIKE ?', ["%{$search}%"]);
            });
        }

        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['charge_start', 'rating', 'created_at', 'name'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min($request->input('per_page', 20), 100);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'shop' => new ShopResource($shop),
                'services' => ServiceResource::collection($services),
                'pagination' => [
                    'current_page' => $services->currentPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                    'last_page' => $services->lastPage(),
                ],
            ],
        ]);
    }
}
