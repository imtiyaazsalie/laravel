<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ConvertRequestsToSnakeCase
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $request->replace(
            $this->convertKeys($request->all()),
        );

        $request->files->replace(
            $this->convertKeys($request->allFiles())
        );

        return $next($request);
    }

    public function convertKeys(array $data): array
    {
        $replaced = [];
        foreach ($data as $key => $value) {
            $replaced[str($key)->snake()->toString()] = is_array($value) ? $this->convertKeys($value) : $value;
        }

        return $replaced;
    }
}
