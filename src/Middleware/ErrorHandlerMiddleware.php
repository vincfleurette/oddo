<?php
// Fix 2: src/Middleware/ErrorHandlerMiddleware.php (UPDATED)

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Exception\HttpNotFoundException;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware de gestion d'erreurs globales avec suppression des warnings PHP
 */
class ErrorHandlerMiddleware
{
    public function __invoke(
        Request $request,
        RequestHandler $handler
    ): Response {
        // Supprimer l'affichage des erreurs PHP pour éviter la pollution JSON
        $originalErrorReporting = error_reporting();
        $originalDisplayErrors = ini_get("display_errors");

        error_reporting(E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR);
        ini_set("display_errors", "0");

        try {
            $response = $handler->handle($request);

            // Vérifier si la réponse contient du HTML d'erreur
            $body = (string) $response->getBody();
            if (
                strpos($body, "<br />") !== false ||
                strpos($body, "<b>Warning</b>") !== false
            ) {
                // La réponse contient des erreurs PHP, retourner une erreur propre
                return $this->createCleanErrorResponse(
                    "Server configuration error"
                );
            }

            return $response;
        } catch (HttpNotFoundException $e) {
            return $this->notFoundResponse();
        } catch (\Throwable $e) {
            error_log(
                "API Error: " .
                    $e->getMessage() .
                    " in " .
                    $e->getFile() .
                    ":" .
                    $e->getLine()
            );
            return $this->errorResponse($e);
        } finally {
            // Restaurer les paramètres d'erreur
            error_reporting($originalErrorReporting);
            ini_set("display_errors", $originalDisplayErrors);
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
            "message" => $this->getSafeErrorMessage($e),
        ];

        // Ajouter des détails de debug uniquement en développement
        if (($_ENV["APP_ENV"] ?? "production") === "development") {
            $data["debug"] = [
                "file" => $e->getFile(),
                "line" => $e->getLine(),
                "type" => get_class($e),
                "trace" => array_slice($e->getTrace(), 0, 5), // Limiter la trace
            ];
        }

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus(500)
            ->withHeader("Content-Type", "application/json");
    }

    private function createCleanErrorResponse(string $message): Response
    {
        $response = new SlimResponse();
        $data = [
            "error" => "Server Error",
            "message" => $message,
            "suggestion" =>
                "Please check server configuration and storage permissions",
        ];

        $response->getBody()->write(json_encode($data));
        return $response
            ->withStatus(500)
            ->withHeader("Content-Type", "application/json");
    }

    private function getSafeErrorMessage(\Throwable $e): string
    {
        // Messages d'erreur sécurisés pour la production
        $safeMessages = [
            "PDOException" => "Database connection error",
            "InvalidArgumentException" => "Invalid configuration",
            "RuntimeException" => "Service temporarily unavailable",
        ];

        $className = get_class($e);
        foreach ($safeMessages as $type => $message) {
            if (strpos($className, $type) !== false) {
                return $message;
            }
        }

        return "An unexpected error occurred";
    }
}
