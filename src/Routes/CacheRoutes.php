<?php

declare(strict_types=1);

namespace App\Routes;

use Slim\App;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use App\Services\CacheService;
use App\Services\AuthService;
use App\Services\OddoApiService;
use App\External\OddoApiClient;
use App\Middleware\JwtMiddleware;

/**
 * Routes de gestion du cache
 */
class CacheRoutes
{
    public function register(App $app, Container $container): void
    {
        $jwtMiddleware = new JwtMiddleware($container->get(AuthService::class));

        $app->group("", function ($group) use ($container) {
            $group->get("/cache/info", function (
                Request $request,
                ResponseInterface $response
            ) use ($container) {
                try {
                    $jwtPayload = $request->getAttribute("jwt");
                    /** @var AuthService $authService */
                    $authService = $container->get(AuthService::class);
                    $credentials = $authService->extractCredentials(
                        $jwtPayload
                    );
                    $userId = $credentials["username"];

                    /** @var CacheService $cacheService */
                    $cacheService = $container->get(CacheService::class);
                    $info = $cacheService->getCacheInfo($userId, "accounts");

                    if ($info === null) {
                        $info = [
                            "exists" => false,
                            "message" => "No cache found for this user",
                        ];
                    }

                    $response->getBody()->write(json_encode($info));
                    return $response->withHeader(
                        "Content-Type",
                        "application/json"
                    );
                } catch (\Exception $e) {
                    $data = [
                        "error" => "Failed to get cache info",
                        "message" => $e->getMessage(),
                    ];
                    $response->getBody()->write(json_encode($data));
                    return $response
                        ->withStatus(500)
                        ->withHeader("Content-Type", "application/json");
                }
            });

            $group->delete("/cache", function (
                Request $request,
                ResponseInterface $response
            ) use ($container) {
                try {
                    $jwtPayload = $request->getAttribute("jwt");
                    /** @var AuthService $authService */
                    $authService = $container->get(AuthService::class);
                    $credentials = $authService->extractCredentials(
                        $jwtPayload
                    );
                    $userId = $credentials["username"];

                    /** @var CacheService $cacheService */
                    $cacheService = $container->get(CacheService::class);
                    $success = $cacheService->invalidateUser($userId);

                    $data = [
                        "success" => $success,
                        "message" => $success
                            ? "Cache invalidated successfully"
                            : "Failed to invalidate cache",
                        "timestamp" => date("c"),
                    ];
                    $response->getBody()->write(json_encode($data));
                    return $response->withHeader(
                        "Content-Type",
                        "application/json"
                    );
                } catch (\Exception $e) {
                    $data = [
                        "error" => "Failed to invalidate cache",
                        "message" => $e->getMessage(),
                    ];
                    $response->getBody()->write(json_encode($data));
                    return $response
                        ->withStatus(500)
                        ->withHeader("Content-Type", "application/json");
                }
            });

            $group->post("/cache/refresh", function (
                Request $request,
                ResponseInterface $response
            ) use ($container) {
                try {
                    $jwtPayload = $request->getAttribute("jwt");
                    /** @var AuthService $authService */
                    $authService = $container->get(AuthService::class);
                    $credentials = $authService->extractCredentials(
                        $jwtPayload
                    );
                    $userId = $credentials["username"];

                    /** @var CacheService $cacheService */
                    $cacheService = $container->get(CacheService::class);

                    // Fonction de récupération des données
                    $dataFetcher = function () use ($container, $credentials) {
                        $apiClient = new OddoApiClient(
                            $container
                                ->get(\App\Config\AppConfig::class)
                                ->getOddoConfig()
                        );
                        $apiClient->setToken($credentials["token"]);
                        $apiClient->setUuid($credentials["uuid"]);

                        $oddoService = new OddoApiService($apiClient);
                        $accounts = $oddoService->fetchAccountsWithPositions();

                        return array_map(
                            fn($dto) => $dto->toArray(),
                            $accounts
                        );
                    };

                    // Rafraîchir le cache
                    $data = $cacheService->refreshUserAccounts(
                        $userId,
                        $dataFetcher
                    );

                    $responseData = [
                        "success" => true,
                        "message" => "Cache refreshed successfully",
                        "accountsCount" => count($data),
                        "timestamp" => date("c"),
                    ];
                    $response->getBody()->write(json_encode($responseData));
                    return $response->withHeader(
                        "Content-Type",
                        "application/json"
                    );
                } catch (\Exception $e) {
                    $data = [
                        "error" => "Failed to refresh cache",
                        "message" => $e->getMessage(),
                    ];
                    $response->getBody()->write(json_encode($data));
                    return $response
                        ->withStatus(500)
                        ->withHeader("Content-Type", "application/json");
                }
            });
        })->add($jwtMiddleware);
    }
}
