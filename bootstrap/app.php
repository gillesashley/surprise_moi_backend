<?php

use App\Http\Middleware\EnsureDashboardAccess;
use App\Http\Middleware\EnsureRiderApproved;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\EnsureUserManagementAccess;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/rider')
                ->group(base_path('routes/api_rider.php'));
        },
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['prefix' => 'api', 'middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies (required for Docker/nginx reverse proxy setup)
        $middleware->trustProxies(
            at: '*', // Trust all proxies (or specify Docker network range like '172.16.0.0/12')
            headers: Request::HEADER_X_FORWARDED_FOR |
                Request::HEADER_X_FORWARDED_HOST |
                Request::HEADER_X_FORWARDED_PORT |
                Request::HEADER_X_FORWARDED_PROTO |
                Request::HEADER_X_FORWARDED_AWS_ELB
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            '.well-known/assetlinks.json',
            '.well-known/apple-app-site-association',
        ]);

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'dashboard' => EnsureDashboardAccess::class,
            'rider.approved' => EnsureRiderApproved::class,
            'role' => EnsureUserRole::class,
            'user-management' => EnsureUserManagementAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
