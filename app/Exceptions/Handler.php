<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Laravel\Passport\Exceptions\OAuthServerException as ExceptionsOAuthServerException;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Customise exception rendering
     */
    public function render($request, Throwable $e)
    {
        /**
         * Http exception.
         */
        // if ($e instanceof HttpException && $request->wantsJson()) {
        //     return response()->errorMessage(
        //         $e->getMessage(),
        //         'Error',
        //         $e->getStatusCode()
        //     );
        // }

        /**
         * Model not found.
         */
        if ($e instanceof ModelNotFoundException && $request->wantsJson()) {
            return response()->json([
                ...parent::render($request, $e)->original,
                'message' => str($e->getModel())->afterLast('\\')->ucsplit()->join(' ').' not found.',
            ], 404);
        }

        return parent::render($request, $e);
    }

    public function report(Throwable $e)
    {

        // Kill reporting of some OAuthServerExceptions
        if (($e instanceof OAuthServerException || $e instanceof ExceptionsOAuthServerException) && in_array($e->getCode(), [
            6, // The user credentials were incorrect
            9, // The resource owner or authorization server denied the request.
        ])) {
            return;
        }

        parent::report($e);
    }
}
