<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Temporary fail-closed policy: the existing admin dashboard exposes
        // global data and is not tenant-scoped. Only an explicitly assigned
        // superadmin may access it until per-resource admin policies exist.
        abort_unless($request->user()?->hasRole('superadmin'), 403);

        return $next($request);
    }
}
