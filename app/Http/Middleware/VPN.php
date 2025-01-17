<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VPN
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            app()->isLocal() || in_array($request->ip(), config('octiv.vpn_ips')),
            Response::HTTP_NOT_FOUND,
            'Not found.'
        );

        return $next($request);
    }
}
