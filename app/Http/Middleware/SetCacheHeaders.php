<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add Cache-Control headers to successful GET responses.
 *
 * Usage in routes: ->middleware('cache.headers:public;max_age=60;s_maxage=300')
 * Parameters are semicolon-separated; underscores become hyphens in header values.
 */
class SetCacheHeaders
{
    public function handle(Request $request, Closure $next, string $options = ''): Response
    {
        $response = $next($request);

        // Only cache GET requests that returned successfully
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return $response;
        }

        // Don't override if response already has Cache-Control set by the controller
        if ($response->headers->has('Cache-Control') && $response->headers->get('Cache-Control') !== 'no-cache, private') {
            return $response;
        }

        if (empty($options)) {
            return $response;
        }

        // Parse options: "public;max_age=60;s_maxage=300"
        $directives = [];
        foreach (explode(';', $options) as $part) {
            $part = trim($part);
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', $part, 2);
                $directives[] = str_replace('_', '-', $key).'='.$value;
            } else {
                $directives[] = str_replace('_', '-', $part);
            }
        }

        $response->headers->set('Cache-Control', implode(', ', $directives));

        // Ensure CDNs vary on auth token so personalized fields (e.g. is_wishlist) aren't shared
        $response->headers->set('Vary', 'Authorization');

        return $response;
    }
}
