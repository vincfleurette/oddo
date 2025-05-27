<?php

namespace App\Routes;

use Slim\App;
use Slim\Psr7\Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;

/**
 * Routes de santé de l'application
 */
class HealthRoutes
{
    public function register(App $app): void
    {
        $app->get("/status", [$this, "healthCheck"]);
    }

    public function healthCheck(
        Request $request,
        ResponseInterface $response
    ): ResponseInterface {
        $data = [
            "status" => "ok",
            "timestamp" => date("c"),
            "version" => "1.0.0",
        ];

        $response->getBody()->write(json_encode($data));
        return $response->withHeader("Content-Type", "application/json");
    }
}
