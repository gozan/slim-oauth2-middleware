<?php
namespace Chadicus\Slim\OAuth2\Middleware;

use ArrayAccess;
use Chadicus\Slim\OAuth2\Http\RequestBridge;
use Chadicus\Slim\OAuth2\Http\ResponseBridge;
use Chadicus\Psr\Middleware\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use OAuth2;
use Slim;

/**
 * Slim Middleware to handle OAuth2 Authorization.
 */
class Authorization implements MiddlewareInterface
{
    /**
     * OAuth2 Server
     *
     * @var OAuth2\Server
     */
    private $server;

    /**
     * Array of scopes required for authorization.
     *
     * @var array
     */
    private $scopes;

    /**
     * Container for token.
     *
     * @var ArrayAccess
     */
    private $container;

    /**
     * Create a new instance of the Authroization middleware.
     *
     * @param OAuth2\Server $server    The configured OAuth2 server.
     * @param ArrayAccess   $container A container object in which to store the token from the request.
     * @param array         $scopes    Scopes required for authorization. $scopes can be given as an array of arrays. OR
     *                                 logic will use with each grouping.  Example:
     *                                 Given ['superUser', ['basicUser', 'aPermission']], the request will be verified
     *                                 if the request token has 'superUser' scope OR 'basicUser' and 'aPermission' as
     *                                 its scope.
     */
    public function __construct(OAuth2\Server $server, ArrayAccess $container, array $scopes = [])
    {
        $this->server = $server;
        $this->container = $container;
        $this->scopes = $scopes;
    }

    /**
     * Execute this middleware.
     *
     * @param  ServerRequestInterface $request  The PSR7 request.
     * @param  ResponseInterface      $response The PSR7 response.
     * @param  callable               $next     The Next middleware.
     *
     * @return Slim\Http\Response
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, callable $next)
    {
        $oauth2Request = RequestBridge::toOAuth2($request);

        $scopes = $this->scopes;
        if (empty($scopes)) {
            $scopes = [null]; //use at least 1 null scope
        }

        foreach ($scopes as $scope) {
            if (is_array($scope)) {
                $scope = implode(' ', $scope);
            }

            if ($this->server->verifyResourceRequest($oauth2Request, null, $scope)) {
                $this->container['token'] = $this->server->getResourceController()->getToken();
                return $next($request, $response);
            }
        }

        return ResponseBridge::fromOAuth2($this->server->getResponse());
    }

    /**
     * Returns a callable function to be used as a authorization middleware with a specified scope.
     *
     * @param array $scopes Scopes require for authorization.
     *
     * @return Authorization
     */
    public function withRequiredScope(array $scopes)
    {
        $clone = clone $this;
        $clone->scopes = $scopes;
        return $clone;
    }
}
