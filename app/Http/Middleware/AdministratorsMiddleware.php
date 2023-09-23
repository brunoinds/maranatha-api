<?php

namespace App\Http\Middleware;

use Closure;

class AdministratorsMiddleware
{
    public function handle($request, Closure $next)
    {
        if (!auth()->user()->isAdmin()) {
            return response('Unauthorized.', 401);
        }
        
        return $next($request);
    }
}