<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\WawVideo\StoreWawVideoRequest;
use App\Http\Resources\WawVideoResource;
use App\Models\WawVideo;
use App\Models\WawVideoLike;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class WawVideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 10), 50);
        $sortBy = in_array($request->input('sort_by'), ['created_at', 'likes_count']) ? $request->input('sort_by') : 'created_at';
        $sortOrder = $request->input('sort_order') === 'asc' ? 'asc' : 'desc';

        $query = WawVideo::with(['vendor', 'product', 'service'])
            ->with(['currentUserLike' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->orderBy($sortBy, $sortOrder);

        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        $videos = $query->paginate($perPage);

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

    public function store(StoreWawVideoRequest $request): JsonResponse
    {
        $vendor = $request->user();

        $videoPath = $request->file('video')->store(
            'waw-videos/'.$vendor->id,
            'r2'
        );

        $thumbnailPath = null;
        if ($request->hasFile('thumbnail')) {
            $thumbnailPath = $request->file('thumbnail')->store(
                'waw-videos/'.$vendor->id.'/thumbs',
                'r2'
            );
        }

        $wawVideo = WawVideo::create([
            'vendor_id' => $vendor->id,
            'video_url' => $videoPath,
            'thumbnail_url' => $thumbnailPath,
            'caption' => $request->input('caption'),
            'product_id' => $request->input('product_id'),
            'service_id' => $request->input('service_id'),
        ]);

        $wawVideo->refresh();
        $wawVideo->load(['vendor', 'product', 'service']);
        $wawVideo->setRelation('currentUserLike', null);

        return response()->json([
            'success' => true,
            'message' => 'Video uploaded successfully.',
            'data' => [
                'video' => new WawVideoResource($wawVideo),
            ],
        ], 201);
    }

    public function toggleLike(Request $request, WawVideo $wawVideo): JsonResponse
    {
        $user = $request->user();

        $liked = DB::transaction(function () use ($wawVideo, $user) {
            $existingLike = WawVideoLike::where('waw_video_id', $wawVideo->id)
                ->where('user_id', $user->id)
                ->first();

            if ($existingLike) {
                $existingLike->delete();
                WawVideo::where('id', $wawVideo->id)->where('likes_count', '>', 0)->decrement('likes_count');

                return false;
            }

            WawVideoLike::create([
                'waw_video_id' => $wawVideo->id,
                'user_id' => $user->id,
            ]);
            $wawVideo->increment('likes_count');

            return true;
        });

        $wawVideo->refresh();

        return response()->json([
            'success' => true,
            'is_liked' => $liked,
            'likes_count' => $wawVideo->likes_count,
        ]);
    }

    public function destroy(Request $request, WawVideo $wawVideo): JsonResponse
    {
        if ($request->user()->cannot('delete', $wawVideo)) {
            return response()->json([
                'success' => false,
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        Storage::disk('r2')->delete($wawVideo->video_url);

        if ($wawVideo->thumbnail_url) {
            Storage::disk('r2')->delete($wawVideo->thumbnail_url);
        }

        $wawVideo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Video deleted successfully.',
        ]);
    }
}
