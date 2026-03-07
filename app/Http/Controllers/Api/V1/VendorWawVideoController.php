<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WawVideoResource;
use App\Models\WawVideo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorWawVideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 50);

        $videos = WawVideo::with(['vendor', 'product', 'service'])
            ->with(['currentUserLike' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->where('vendor_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'videos' => WawVideoResource::collection($videos),
                'pagination' => [
                    'current_page' => $videos->currentPage(),
                    'per_page' => $videos->perPage(),
                    'total' => $videos->total(),
                    'last_page' => $videos->lastPage(),
                ],
            ],
        ]);
    }
}
