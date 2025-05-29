<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware de gestion d'erreurs globales
 */
class ErrorHandlerMiddleware
{
    public function __invoke(
        Request $request,
        RequestHandler $handler
    ): Response {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function notFoundResponse(): Response
    {
        $response = new SlimResponse();
        $data = [
            "error" => "Not Found",
            "message" => "The requested resource was not found",
        ];

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus(404)
            ->withHeader("Content-Type", "application/json");
    }

    private function errorResponse(\Throwable $e): Response
    {
        $response = new SlimResponse();
        $data = [
            "error" => "Internal Server Error",
            "message" => $e->getMessage(),
        ];

        // Ajouter des détails de debug uniquement en développement
        if (($_ENV["APP_ENV"] ?? "production") === "development") {
            $data["debug"] = [
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "type" => get_class($e),
            ];
        }

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus(500)
            ->withHeader("Content-Type", "application/json");
    }
}
