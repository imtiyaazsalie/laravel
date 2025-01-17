<?php

namespace App\Http\Middleware;

// todo: did this because our payloads are currently quite big, needs to be optimized
ini_set('memory_limit', '2048M');

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConvertResponseToCamelCase
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {

        if (! $request->header('X-CamelCase') || ! $request->header('X-Camelcase')) {
            return $next($request);
        }

        $response = $next($request);

        try {
            $data = json_decode($response->getContent(), true);

            $response->setContent(
                json_encode(
                    is_array($data) ? $this->convertKeys($data) : $data
                )
            );
        } catch (\Exception $e) {
            // you can log an error here if you want
        }

        return $response;
    }

    public function convertKeys(array $data): array
    {
        $replaced = [];
        foreach ($data as $key => $value) {
            $replaced[str($key)->camel()->toString()] = is_array($value) ? $this->convertKeys($value) : $value;
        }

        return $replaced;
    }

    private function transformKeysToCamelCase($data): array|object
    {
        $result = [];
        foreach ($data as $key => $value) {
            // Here we use the Str::camel() method from Laravel
            $camelKey = Str::camel($key);
            $result[$camelKey] = is_array($value) ? $this->transformKeysToCamelCase($value) : $value;
        }

        return $result;
    }
}
