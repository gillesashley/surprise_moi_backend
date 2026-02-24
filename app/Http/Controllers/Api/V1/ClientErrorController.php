<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClientErrorRequest;
use App\Models\ClientError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientErrorController extends Controller
{
    /**
     * Store a client-side error reported by frontend/mobile.
     */
    public function store(StoreClientErrorRequest $request): JsonResponse
    {
        $data = $request->validated();

        $record = ClientError::create([
            'user_id' => $data['user_id'] ?? null,
            'device_info' => $data['device_info'] ?? null,
            'occurred_at' => $data['time'] ?? null,
            'error' => $data['error'] ?? null,
            'payload' => $data['payload'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
        ]);

        return response()->json(['data' => $record], 201);
    }
}
