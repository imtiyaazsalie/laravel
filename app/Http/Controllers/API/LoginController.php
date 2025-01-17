<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use GuzzleHttp\Psr7\ServerRequest;
use Illuminate\Http\Response;
use Laravel\Passport\Client;
use Laravel\Passport\Exceptions\OAuthServerException;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use Laravel\Passport\TokenRepository;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Response as Psr7Response;
use Psr\Http\Message\ServerRequestInterface;

class LoginController
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
     * @return Response
     *
     * @throws OAuthServerException
     */
    public function login(ServerRequestInterface $request)
    {
        $client = Client::findOrFail(2);

        $request = (new ServerRequest($request->getMethod(), $request->getUri(), $request->getHeaders()))
            ->withParsedBody([
                ...$request->getParsedBody(),
                'grant_type' => 'password',
                'client_id' => $client->getKey(),
                'client_secret' => $client->secret,
                'scope' => '',
            ]);

        return $this->withErrorHandling(function () use ($request) {
            $response = $this->convertResponse(
                $this->server->respondToAccessTokenRequest($request, new Psr7Response)
            );

            $content = json_decode($response->content());
            $content->merge_account = $this->isUserDuplicate($request->getParsedBody()['username']);

            return $response->setContent(json_encode($content));
        });
    }

    private function isUserDuplicate($email)
    {
        $result = User::where('email', '=', $email)->count();

        if ($result > 1) {
            return true;
        }

        return false;
    }
}
