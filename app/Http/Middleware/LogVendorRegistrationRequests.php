<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogVendorRegistrationRequests
{
    /**
     * Handle an incoming request.
     * Logs all vendor registration requests for debugging.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only log vendor registration routes
        if (str_contains($request->path(), 'vendor-registration')) {
            Log::channel('stack')->info('Vendor Registration Request', [
                'method' => $request->method(),
                'path' => $request->path(),
                'user_id' => auth()->id(),
                'user_email' => auth()->user()?->email,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'payload' => $request->except(['ghana_card_front', 'ghana_card_back', 'selfie_image', 'business_certificate_document', 'tin_document', 'proof_of_business']),
                'files' => $request->files->keys(),
                'timestamp' => now()->toIso8601String(),
            ]);
        }

        $response = $next($request);

        // Log response for vendor registration
        if (str_contains($request->path(), 'vendor-registration')) {
            Log::channel('stack')->info('Vendor Registration Response', [
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'user_id' => auth()->id(),
                'response_preview' => $response instanceof \Illuminate\Http\JsonResponse
                    ? substr(json_encode($response->getData()), 0, 500)
                    : 'Non-JSON response',
            ]);
        }

        return $response;
    }
}
