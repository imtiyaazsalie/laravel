<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class NetcashHosts
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->getScheme() !== 'https') {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validHosts = [
            'paynow.netcash.co.za',
        ];

        $referrerIp = $request->ip();

        foreach ($validHosts as $host) {
            $ips = gethostbynamel($host);

            if ($ips !== false) {
                if (in_array($referrerIp, $ips, true)) {
                    return $next($request);
                }
            }
        }

        Log::emergency('NetCash ITN request received from invalid host', [
            'host' => $referrerIp,
        ]);

        abort(Response::HTTP_FORBIDDEN);
    }
}
