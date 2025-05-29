<?php

declare(strict_types=1);

namespace App\Routes;

use Slim\App;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use App\Services\OddoApiService;
use App\Services\CacheService;
use App\Services\PortfolioService;
use App\Services\AuthService;
use App\External\OddoApiClient;
use App\Middleware\JwtMiddleware;

/**
 * Routes des comptes
 */
class AccountRoutes
{
    public function register(App $app, Container $container): void
    {
        $jwtMiddleware = new JwtMiddleware($container->get(AuthService::class));

        $app->group("", function ($group) use ($container) {
            $group->get("/accounts", function (
                Request $request,
                ResponseInterface $response
            ) use ($container) {
                try {
                    // Récupérer les informations utilisateur depuis le JWT
                    $jwtPayload = $request->getAttribute("jwt");

                    /** @var AuthService $authService */
                    $authService = $container->get(AuthService::class);
                    $credentials = $authService->extractCredentials(
                        $jwtPayload
                    );

                    $userId = $credentials["username"];

                    /** @var CacheService $cacheService */
                    $cacheService = $container->get(CacheService::class);

                    // Tenter de récupérer depuis le cache
                    $cachedData = $cacheService->getUserAccounts($userId);
                    if ($cachedData !== null) {
                        /** @var PortfolioService $portfolioService */
                        $portfolioService = $container->get(
                            PortfolioService::class
                        );
                        $responseWithStats = $portfolioService->calculateStats(
                            $cachedData
                        );

                        $response
                            ->getBody()
                            ->write(json_encode($responseWithStats));
                        return $response->withHeader(
                            "Content-Type",
                            "application/json"
                        );
                    }

                    // Récupération fraîche - Create a NEW API client instance
                    /** @var \App\Config\AppConfig $appConfig */
                    $appConfig = $container->get(\App\Config\AppConfig::class);
                    $apiClient = new OddoApiClient($appConfig->getOddoConfig());
                    $apiClient->setToken($credentials["token"]);
                    $apiClient->setUuid($credentials["uuid"]);

                    /** @var OddoApiService $oddoService */
                    $oddoService = new OddoApiService($apiClient);
                    $accounts = $oddoService->fetchAccountsWithPositions();

                    // Convertir en array pour le cache
                    $accountsArray = array_map(
                        fn($dto) => $dto->toArray(),
                        $accounts
                    );

                    // Sauvegarder en cache
                    $cacheService->setUserAccounts($userId, $accountsArray);

                    // Calculer les statistiques
                    /** @var PortfolioService $portfolioService */
                    $portfolioService = $container->get(
                        PortfolioService::class
                    );
                    $responseWithStats = $portfolioService->calculateStats(
                        $accountsArray
                    );

                    $response
                        ->getBody()
                        ->write(json_encode($responseWithStats));
                    return $response->withHeader(
                        "Content-Type",
                        "application/json"
                    );
                } catch (\Exception $e) {
                    $data = [
                        "error" => "Failed to fetch accounts",
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
