<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserManagementAccess
{
    /**
     * Handle an incoming request.
     *
     * Ensures the user has verified the user management access code
     * within the configured timeout window.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $accessCode = config('auth.user_management_access_code');

        if (empty($accessCode)) {
            abort(403, 'User management access code is not configured.');
        }

        $verifiedAt = $request->session()->get('user_management.verified_at');
        $timeout = config('auth.user_management_timeout', 1200);

        if (! $verifiedAt || (time() - $verifiedAt) > $timeout) {
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->route('user-management-access.show');
        }

        // Refresh the timestamp on each active request (sliding window),
        // so the session only expires after inactivity.
        $request->session()->put('user_management.verified_at', time());

        return $next($request);
    }
}
