<?php

declare(strict_types=1);

namespace App\Routes;

use Slim\App;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use App\Services\AuthService;

/**
 * Routes d'authentification
 */
class AuthRoutes
{
    public function register(App $app, Container $container): void
    {
        $app->post("/login", function (
            Request $request,
            ResponseInterface $response
        ) use ($container) {
            try {
                $body = json_decode((string) $request->getBody(), true);
                $username = $body["user"] ?? "";
                $password = $body["pass"] ?? "";

                if (!$username || !$password) {
                    $data = ["error" => "Missing credentials"];
                    $response->getBody()->write(json_encode($data));
                    return $response
                        ->withStatus(400)
                        ->withHeader("Content-Type", "application/json");
                }

                /** @var AuthService $authService */
                $authService = $container->get(AuthService::class);
                $jwt = $authService->authenticate($username, $password);

                if (!$jwt) {
                    $data = ["error" => "Invalid credentials"];
                    $response->getBody()->write(json_encode($data));
                    return $response
                        ->withStatus(401)
                        ->withHeader("Content-Type", "application/json");
                }

                $data = ["jwt" => $jwt];
                $response->getBody()->write(json_encode($data));
                return $response->withHeader(
                    "Content-Type",
                    "application/json"
                );
            } catch (\Exception $e) {
                $data = [
                    "error" => "Authentication failed",
                    "message" => $e->getMessage(),
                ];
                $response->getBody()->write(json_encode($data));
                return $response
                    ->withStatus(500)
                    ->withHeader("Content-Type", "application/json");
            }
        });
    }
}
