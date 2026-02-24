<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserRole Middleware - Restrict routes to specific user roles.
 *
 * Usage in routes:
 * Route::middleware('role:vendor')->group(...)
 * Route::middleware('role:admin,super_admin')->group(...)  // Multiple roles
 *
 * Available roles:
 * - customer (default)
 * - vendor
 * - admin
 * - super_admin
 * - influencer
 * - field_agent
 * - marketer
 *
 * Returns 403 if user doesn't have required role.
 */
class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * Validates that authenticated user has one of the allowed roles.
     * Supports comma-separated roles (e.g., 'admin,super_admin') or
     * multiple arguments (e.g., 'admin', 'super_admin').
     *
     * @param  Request  $request  The incoming request
     * @param  Closure  $next  Next middleware
     * @param  string  ...$roles  Comma-separated roles or multiple role arguments
     * @return Response 401 if unauthenticated, 403 if wrong role, or continues
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Check if user is authenticated
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Parse roles: handle both comma-separated and multiple arguments
        // Supports: role:admin,vendor OR role:admin:vendor
        $allowedRoles = [];
        foreach ($roles as $role) {
            if (str_contains($role, ',')) {
                $allowedRoles = array_merge($allowedRoles, explode(',', $role));
            } else {
                $allowedRoles[] = $role;
            }
        }

        // Check if user has any of the allowed roles
        if (! in_array($request->user()->role, $allowedRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have permission to access this resource.',
            ], 403);
        }

        return $next($request);
    }
}
