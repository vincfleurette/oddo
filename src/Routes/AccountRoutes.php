<?php

/**
 * Routes des comptes
 */
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
                return $this->getAccountsWithPositions(
                    $request,
                    $response,
                    $container
                );
            });
        })->add($jwtMiddleware);
    }

    public function getAccountsWithPositions(
        Request $request,
        ResponseInterface $response,
        Container $container
    ): ResponseInterface {
        try {
            // Récupérer les informations utilisateur depuis le JWT
            $jwtPayload = $request->getAttribute("jwt");
            /** @var AuthService $authService */
            $authService = $container->get(AuthService::class);
            $credentials = $authService->extractCredentials($jwtPayload);

            $userId = $credentials["username"];

            /** @var CacheService $cacheService */
            $cacheService = $container->get(CacheService::class);

            // Tenter de récupérer depuis le cache
            $cachedData = $cacheService->getUserAccounts($userId);
            if ($cachedData !== null) {
                /** @var PortfolioService $portfolioService */
                $portfolioService = $container->get(PortfolioService::class);
                $responseWithStats = $portfolioService->calculateStats(
                    $cachedData
                );

                return $this->jsonResponse($response, $responseWithStats);
            }

            // Récupération fraîche
            $apiClient = new OddoApiClient(
                $container->get(\App\Config\AppConfig::class)->getOddoConfig()
            );
            $apiClient->setToken($credentials["token"]);
            $apiClient->setUuid($credentials["uuid"]);

            /** @var OddoApiService $oddoService */
            $oddoService = new OddoApiService($apiClient);
            $accounts = $oddoService->fetchAccountsWithPositions();

            // Convertir en array pour le cache
            $accountsArray = array_map(fn($dto) => $dto->toArray(), $accounts);

            // Sauvegarder en cache
            $cacheService->setUserAccounts($userId, $accountsArray);

            // Calculer les statistiques
            /** @var PortfolioService $portfolioService */
            $portfolioService = $container->get(PortfolioService::class);
            $responseWithStats = $portfolioService->calculateStats(
                $accountsArray
            );

            return $this->jsonResponse($response, $responseWithStats);
        } catch (\Exception $e) {
            return $this->jsonResponse(
                $response,
                [
                    "error" => "Failed to fetch accounts",
                    "message" => $e->getMessage(),
                ],
                500
            );
        }
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
