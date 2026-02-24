<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Health check endpoint.
     */
    public function index(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'service' => 'Surprise Moi API',
            'version' => config('app.version', '1.0.0'),
        ];

        // Check database connection
        try {
            DB::connection()->getPdo();
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $health['database'] = 'disconnected';
            $health['status'] = 'error';
        }

        $statusCode = $health['status'] === 'ok' ? 200 : 503;

        return response()->json([
            'success' => true,
            'data' => $health,
        ], $statusCode);
    }
}
