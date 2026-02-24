<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Service::query()
            ->with(['vendor', 'shop'])
            ->where('availability', 'available');

        // Filter by service type
        if ($request->filled('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        // Search in name and description
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Filter by charge range
        if ($request->filled('charge_min')) {
            $query->where('charge_start', '>=', $request->charge_min);
        }

        if ($request->filled('charge_max')) {
            $query->where('charge_start', '<=', $request->charge_max);
        }

        // Filter by vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->vendor_id);
        }

        // Filter by location (vendor's location)
        if ($request->filled('location')) {
            $query->whereHas('vendor', function ($q) use ($request) {
                $q->where('bio', 'LIKE', "%{$request->location}%");
            });
        }

        // Filter by minimum rating
        if ($request->filled('rating_min')) {
            $query->where('rating', '>=', $request->rating_min);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        $allowedSorts = ['charge_start', 'rating', 'created_at', 'name'];
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = min($request->input('per_page', 20), 100);
        $services = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
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
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $service = Service::with(['vendor', 'shop'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'service' => new ServiceResource($service),
            ],
        ]);
    }

    /**
     * Store a newly created service.
     */
    public function store(\App\Http\Requests\Api\V1\Service\StoreServiceRequest $request)
    {
        $data = $request->validated();
        $data['vendor_id'] = $request->user()->id;

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            $data['thumbnail'] = $request->file('thumbnail')->store('services/thumbnails', 'public');
        }

        $service = Service::create($data);
        $service->load(['vendor', 'shop']);

        return response()->json([
            'success' => true,
            'message' => 'Service created successfully.',
            'data' => [
                'service' => new ServiceResource($service),
            ],
        ], 201);
    }

    /**
     * Update the specified service.
     */
    public function update(\App\Http\Requests\Api\V1\Service\UpdateServiceRequest $request, Service $service)
    {
        $data = $request->validated();

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail')) {
            // Delete old thumbnail if it exists
            if ($service->thumbnail) {
                Storage::disk('public')->delete($service->thumbnail);
            }

            $data['thumbnail'] = $request->file('thumbnail')->store('services/thumbnails', 'public');
        }

        $service->update($data);
        $service->load(['vendor', 'shop']);

        return response()->json([
            'success' => true,
            'message' => 'Service updated successfully.',
            'data' => [
                'service' => new ServiceResource($service),
            ],
        ]);
    }

    /**
     * Remove the specified service.
     */
    public function destroy(Service $service)
    {
        // Check authorization
        if ($service->vendor_id !== request()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this service.',
            ], 403);
        }

        // Delete thumbnail from storage
        if ($service->thumbnail) {
            Storage::disk('public')->delete($service->thumbnail);
        }

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Service deleted successfully.',
        ]);
    }
}
