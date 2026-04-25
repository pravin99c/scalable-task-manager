<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     * Only allows users with the 'admin' role.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && in_array(Auth::user()->role, ['superadmin', 'admin'])) {
            return $next($request);
        }

        return redirect()->route('admin.login')->with('error', 'Unauthorized access.');
    }
}
