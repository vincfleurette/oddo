<?php

/**
 * Routes d'authentification
 */
namespace App\Routes;

use Slim\App;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use App\Services\AuthService;

class AuthRoutes
{
    public function register(App $app, Container $container): void
    {
        $app->post("/login", function (
            Request $request,
            ResponseInterface $response
        ) use ($container) {
            return $this->login($request, $response, $container);
        });
    }

    public function login(
        Request $request,
        ResponseInterface $response,
        Container $container
    ): ResponseInterface {
        $body = json_decode((string) $request->getBody(), true);
        $username = $body["user"] ?? "";
        $password = $body["pass"] ?? "";

        if (!$username || !$password) {
            return $this->jsonResponse(
                $response,
                ["error" => "Missing credentials"],
                400
            );
        }

        /** @var AuthService $authService */
        $authService = $container->get(AuthService::class);
        $jwt = $authService->authenticate($username, $password);

        if (!$jwt) {
            return $this->jsonResponse(
                $response,
                ["error" => "Invalid credentials"],
                401
            );
        }

        return $this->jsonResponse($response, ["jwt" => $jwt]);
    }

    private function jsonResponse(
        ResponseInterface $response,
        array $data,
        int $status = 200
    ): ResponseInterface {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader("Content-Type", "application/json")
            ->withStatus($status);
    }
}
