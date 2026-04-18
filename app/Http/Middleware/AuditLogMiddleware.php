<?php

namespace App\Http\Middleware;

use App\Support\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        AuditContext::set($request->ip(), $request->userAgent());

        try {
            return $next($request);
        } finally {
            AuditContext::forget();
        }
    }
}
