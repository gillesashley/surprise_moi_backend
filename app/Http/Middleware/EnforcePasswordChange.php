<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforcePasswordChange
{
    /**
     * Path prefixes that a flagged user is still allowed to reach without
     * redirecting, so they can complete the password change or log out.
     *
     * @var list<string>
     */
    private const ALLOWED_PREFIXES = [
        'settings/password',
        'logout',
        'login',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! (bool) $user->must_change_password) {
            return $next($request);
        }

        $path = $request->path();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                return $next($request);
            }
        }

        return redirect('/settings/password')
            ->with('error', 'You must change your default password before continuing.');
    }
}
