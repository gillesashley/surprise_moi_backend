<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureDashboardAccess
{
    /**
     * Handle an incoming request.
     * Routes users to their appropriate role-specific dashboards.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->canAccessDashboard()) {
            if ($user) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->route('login')->withErrors([
                'email' => 'Access denied. You do not have permission to access the dashboard.',
            ]);
        }

        // Get the current path
        $currentPath = $request->path();

        // Redirect users to their role-specific dashboards if they try to access wrong dashboard

        // Influencers should only access /influencer/* routes
        if ($user->role === 'influencer' && ! str_starts_with($currentPath, 'influencer')) {
            return redirect()->route('influencer.dashboard');
        }

        // Field agents should only access /field-agent/* routes
        if ($user->role === 'field_agent' && ! str_starts_with($currentPath, 'field-agent')) {
            return redirect()->route('field-agent.dashboard');
        }

        // Marketers should only access /marketer/* routes
        if ($user->role === 'marketer' && ! str_starts_with($currentPath, 'marketer')) {
            return redirect()->route('marketer.dashboard');
        }

        // Admins and super admins can access /dashboard and all admin routes
        if (in_array($user->role, ['admin', 'super_admin'])) {
            // Prevent admins from accessing role-specific routes
            if (
                str_starts_with($currentPath, 'influencer') ||
                str_starts_with($currentPath, 'field-agent') ||
                str_starts_with($currentPath, 'marketer')
            ) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
