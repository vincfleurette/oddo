<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use App\Services\AuthService;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware d'authentification JWT
 */
class JwtMiddleware
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function __invoke(
        Request $request,
        RequestHandler $handler
    ): Response {
        $auth = $request->getHeaderLine("Authorization");

        if (!preg_match('/Bearer\s+(.+)$/', $auth, $matches)) {
            return $this->unauthorizedResponse("Missing Bearer token");
        }

        $token = $this->authService->validateToken($matches[1]);

        if ($token === null) {
            return $this->unauthorizedResponse("Invalid token");
        }

        return $handler->handle($request->withAttribute("jwt", $token));
    }

    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response->getBody()->write(json_encode(["error" => $message]));
        return $response
            ->withStatus(401)
            ->withHeader("Content-Type", "application/json");
    }
}
