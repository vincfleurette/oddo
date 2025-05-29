<?php
declare(strict_types=1);

namespace App\Routes;

use Slim\App;
use DI\Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface;
use App\Services\PortfolioService;
use App\Services\AuthService;
use App\Middleware\JwtMiddleware;

class PortfolioRoutes
{
    public function register(App $app, Container $container): void
    {
        $jwtMiddleware = new JwtMiddleware($container->get(AuthService::class));

        $app->group("", function ($group) use ($container) {
            $group->get("/portfolio/overview", function (
                Request $request,
                ResponseInterface $response
            ) use ($container) {
                try {
                    $jwtPayload = $request->getAttribute("jwt");
                    $authService = $container->get(AuthService::class);
                    $credentials = $authService->extractCredentials(
                        $jwtPayload
                    );
                    $userId = $credentials["username"];

                    $cacheService = $container->get(
                        \App\Services\CacheService::class
                    );
                    $cachedData = $cacheService->getUserAccounts($userId);

                    if ($cachedData === null) {
                        $response->getBody()->write(
                            json_encode([
                                "error" => "No portfolio data available",
                                "message" => "Please refresh your data first",
                            ])
                        );
                        return $response
                            ->withStatus(404)
                            ->withHeader("Content-Type", "application/json");
                    }

                    $portfolioService = $container->get(
                        PortfolioService::class
                    );
                    $stats = $portfolioService->calculateStats($cachedData);

                    // Retourner seulement les stats du portfolio pour la vue overview
                    $response
                        ->getBody()
                        ->write(json_encode($stats["portfolio"]));
                    return $response->withHeader(
                        "Content-Type",
                        "application/json"
                    );
                } catch (\Exception $e) {
                    $data = [
                        "error" => "Failed to fetch portfolio overview",
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
