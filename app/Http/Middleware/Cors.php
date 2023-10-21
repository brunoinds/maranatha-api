<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Cors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        return $next($request)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        
        $requestMethod = $request->server('REQUEST_METHOD');
        $requestOrigin = $request->server('HTTP_ORIGIN');
        $nextResponse = $next($request);
        

        if ($requestOrigin){
            $nextResponse->headers->set('Access-Control-Allow-Origin', $requestOrigin);
            $nextResponse->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        if ($requestMethod === 'OPTIONS'){
            if ($request->server('HTTP_ACCESS_CONTROL_REQUEST_METHOD')){
                $nextResponse->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            }
            if ($request->server('HTTP_ACCESS_CONTROL_REQUEST_HEADERS')){
                $nextResponse->headers->set('Access-Control-Allow-Headers', $request->server('HTTP_ACCESS_CONTROL_REQUEST_HEADERS'));
            }
        }
        
        return $nextResponse;
    }
}
