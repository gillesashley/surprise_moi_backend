<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateVendorRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\VendorResource;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorController extends Controller
{
    /**
     * List all vendors with their product/service counts.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query()
            ->where('role', 'vendor')
            ->withCount(['products', 'services']);

        // Search by name or bio (case-insensitive)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('bio', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by service type
        if ($request->filled('service_type')) {
            $query->whereHas('services', function ($q) use ($request) {
                $q->where('service_type', $request->input('service_type'));
            });
        }

        // Filter by popularity
        if ($request->filled('is_popular')) {
            $query->where('is_popular', filter_var($request->input('is_popular'), FILTER_VALIDATE_BOOLEAN));
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['name', 'created_at', 'products_count', 'services_count'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        $perPage = min($request->input('per_page', 20), 100);
        $vendors = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'vendors' => VendorResource::collection($vendors),
                'pagination' => [
                    'current_page' => $vendors->currentPage(),
                    'per_page' => $vendors->perPage(),
                    'total' => $vendors->total(),
                    'last_page' => $vendors->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * Show a single vendor profile with basic stats.
     */
    public function show(int $vendorId): JsonResponse
    {
        $vendor = User::where('role', 'vendor')
            ->withCount(['products', 'services'])
            ->withAvg('products', 'rating')
            ->withAvg('services', 'rating')
            ->findOrFail($vendorId);

        return response()->json([
            'success' => true,
            'data' => [
                'vendor' => new VendorResource($vendor),
            ],
        ]);
    }

    /**
     * Get all products from a specific vendor.
     */
    public function products(Request $request, int $vendorId): JsonResponse
    {
        $vendor = User::where('role', 'vendor')->findOrFail($vendorId);

        $query = Product::query()
            ->with(['category', 'vendor', 'images', 'tags'])
            ->where('vendor_id', $vendorId)
            ->where('is_available', true);

        // Filter by category
        if ($request->filled('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->input('category'));
            });
        }

        // Search in name and description (case-insensitive)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by price range
        if ($request->filled('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }

        if ($request->filled('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
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
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                ],
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
     * Get all services from a specific vendor.
     */
    public function services(Request $request, int $vendorId): JsonResponse
    {
        $vendor = User::where('role', 'vendor')->findOrFail($vendorId);

        $query = Service::query()
            ->with(['vendor'])
            ->where('vendor_id', $vendorId)
            ->where('availability', 'available');

        // Filter by service type
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->input('service_type'));
        }

        // Search in name and description (case-insensitive)
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%");
            });
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
                'vendor' => [
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                ],
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

    /**
     * Update vendor popularity status (admin only).
     */
    public function update(int $vendorId, UpdateVendorRequest $request): JsonResponse
    {
        $vendor = User::where('role', 'vendor')->findOrFail($vendorId);

        $vendor->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Vendor popularity status updated successfully.',
            'data' => [
                'vendor' => new VendorResource($vendor),
            ],
        ]);
    }
}
