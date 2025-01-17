<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Passport\Token;
use Lcobucci\JWT\Encoding\CannotDecodeContent;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\InvalidTokenStructure;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\UnsupportedHeaderFound;
use Symfony\Component\HttpFoundation\Response;

class DiscoveryVitality
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        $parser = new Parser(new JoseEncoder());

        try {
            $token = $parser->parse($bearerToken);

            $tokenId = $token->claims()->get('jti');

            $client = Token::find($tokenId)->client;

            abort_unless(
                $client->name === 'discovery',
                403,
                'Discovery only.'
            );

            $request->merge([
                'passportClient' => $client,
            ]);

        } catch (CannotDecodeContent|InvalidTokenStructure|UnsupportedHeaderFound $e) {

            report($e);

            abort(400, 'Invalid token.');
        }

        return $next($request);
    }
}
