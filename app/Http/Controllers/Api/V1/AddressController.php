<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Address\StoreAddressRequest;
use App\Http\Requests\Api\V1\Address\UpdateAddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AddressController extends Controller
{
    /**
     * Display a listing of the user's addresses.
     */
    public function index(): JsonResponse
    {
        $addresses = auth()->user()
            ->addresses()
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => AddressResource::collection($addresses),
        ]);
    }

    /**
     * Store a newly created address.
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();
            $data['user_id'] = auth()->id();

            // If this is set as default, unset other defaults
            if (! empty($data['is_default'])) {
                auth()->user()->addresses()->update(['is_default' => false]);
            } else {
                // If no default exists, make this the default
                $hasDefault = auth()->user()->addresses()->where('is_default', true)->exists();
                if (! $hasDefault) {
                    $data['is_default'] = true;
                }
            }

            $address = Address::create($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Address created successfully',
                'data' => new AddressResource($address),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified address.
     */
    public function show(Address $address): JsonResponse
    {
        // Check ownership
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new AddressResource($address),
        ]);
    }

    /**
     * Update the specified address.
     */
    public function update(UpdateAddressRequest $request, Address $address): JsonResponse
    {
        DB::beginTransaction();

        try {
            $data = $request->validated();

            // If this is set as default, unset other defaults
            if (! empty($data['is_default'])) {
                auth()->user()->addresses()
                    ->where('id', '!=', $address->id)
                    ->update(['is_default' => false]);
            }

            $address->update($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'data' => new AddressResource($address->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified address.
     */
    public function destroy(Address $address): JsonResponse
    {
        // Check ownership
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $wasDefault = $address->is_default;
            $address->delete();

            // If deleted address was default, set another as default
            if ($wasDefault) {
                $newDefault = auth()->user()->addresses()->first();
                if ($newDefault) {
                    $newDefault->update(['is_default' => true]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Set an address as the default.
     */
    public function setDefault(Address $address): JsonResponse
    {
        // Check ownership
        if ($address->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        DB::beginTransaction();

        try {
            // Unset all other defaults
            auth()->user()->addresses()->update(['is_default' => false]);

            // Set this as default
            $address->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default address updated successfully',
                'data' => new AddressResource($address->fresh()),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to set default address',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
