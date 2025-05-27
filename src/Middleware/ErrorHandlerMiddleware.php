<?php

/**
 * Middleware de gestion d'erreurs
 */
namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response as SlimResponse;

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
        $response->getBody()->write(json_encode(["error" => "Not Found"]));
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

        // En développement, ajouter plus de détails
        if ($_ENV["APP_ENV"] ?? "production" !== "production") {
            $data["file"] = $e->getFile();
            $data["line"] = $e->getLine();
            $data["trace"] = $e->getTraceAsString();
        }

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus(500)
            ->withHeader("Content-Type", "application/json");
    }
}
