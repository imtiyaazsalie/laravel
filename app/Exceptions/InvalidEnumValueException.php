<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class InvalidEnumValueException extends Exception
{
    public function __construct(public $property)
    {
        //
    }

    /**
     * Render exception method.
     */
    public function render(): JsonResponse
    {
        return response()->json(
            [
                'message' => 'Field '.$this->property.' incorrect',
            ],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
