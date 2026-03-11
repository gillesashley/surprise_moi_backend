<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRiderApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $rider = $request->user('rider');

        if (! $rider) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if ($rider->isSuspended()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.',
            ], 403);
        }

        if (! $rider->isApproved()) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is pending approval.',
                'data' => ['status' => $rider->status],
            ], 403);
        }

        return $next($request);
    }
}
