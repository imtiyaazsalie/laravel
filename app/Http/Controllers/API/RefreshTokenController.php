<?php

namespace App\Http\Controllers\API;

use GuzzleHttp\Psr7\ServerRequest;
use Laravel\Passport\Client;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;

class RefreshTokenController
{
    use HandlesOAuthErrors;

    /**
     * The authorization server.
     *
     * @var \League\OAuth2\Server\AuthorizationServer
     */
    protected $server;

    /**
     * The token repository instance.
     *
     * @var \Laravel\Passport\TokenRepository
     */
    protected $tokens;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(AuthorizationServer $server,
        TokenRepository $tokens)
    {
        $this->server = $server;
        $this->tokens = $tokens;
    }

    /**
     * Authorize a client to access the user's account.
     *
     * @return \Illuminate\Http\Response
     */
    public function refresh(ServerRequestInterface $request)
    {
        $client = Client::findOrFail(2);

        $request = (new ServerRequest($request->getMethod(), $request->getUri(), $request->getHeaders()))
            ->withParsedBody([
                ...$request->getParsedBody(),
                'grant_type' => 'refresh_token',
                'client_id' => $client->getKey(),
                'client_secret' => $client->secret,
                'scope' => '',
            ]);

        return $this->withErrorHandling(function () use ($request) {
            return $this->convertResponse(
                $this->server->respondToAccessTokenRequest($request, new Psr7Response)
            );
        });
    }
}
