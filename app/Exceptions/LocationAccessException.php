<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class LocationAccessException extends Exception
{
    /**
     * Report the exception.
     */
    public function report(): bool
    {
        return false;
    }

    /**
     * Render exception method.
     */
    public function render(): JsonResponse
    {
        return response()->json(
            [
                'message' => 'Membership may not access this facility.',
            ],
            Response::HTTP_FORBIDDEN
        );
    }
}
