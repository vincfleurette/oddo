<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use DI\Container;
use App\Config\AppConfig;
use App\Middleware\ErrorHandlerMiddleware;
use App\Routes\AuthRoutes;
use App\Routes\AccountRoutes;
use App\Routes\CacheRoutes;
use App\Routes\HealthRoutes;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->safeLoad();

// Create DI container
$container = new Container();

// Register configuration
$config = new AppConfig();
$container->set(AppConfig::class, $config);

// Register storage manager
$container->set(\App\Storage\StorageManager::class, function () use ($config) {
    return new \App\Storage\StorageManager($config->getStorageConfig());
});

// Register API client
$container->set(\App\External\OddoApiClient::class, function () use ($config) {
    return new \App\External\OddoApiClient($config->getOddoConfig());
});

// Register services
$container->set(\App\Services\CacheService::class, function ($container) use (
    $config
) {
    return new \App\Services\CacheService(
        $container->get(\App\Storage\StorageManager::class),
        $config->getCacheTtl()
    );
});

$container->set(\App\Services\AuthService::class, function () use ($config) {
    $apiClient = new \App\External\OddoApiClient($config->getOddoConfig());
    return new \App\Services\AuthService($apiClient, $config->getJwtSecret());
});

$container->set(\App\Services\OddoApiService::class, function ($container) {
    return new \App\Services\OddoApiService(
        $container->get(\App\External\OddoApiClient::class)
    );
});

$container->set(\App\Services\PortfolioService::class, function () {
    return new \App\Services\PortfolioService();
});

// Create Slim application
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middleware
$app->add(new ErrorHandlerMiddleware());

// Add trailing slash middleware
$app->add(function ($request, $handler) {
    $uri = $request->getUri();
    $path = $uri->getPath();

    if ($path !== "/" && str_ends_with($path, "/")) {
        $newUri = $uri->withPath(rtrim($path, "/"));
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader("Location", (string) $newUri)
            ->withStatus(301);
    }

    return $handler->handle($request);
});

// Register routes
(new HealthRoutes())->register($app);
(new AuthRoutes())->register($app, $container);
(new AccountRoutes())->register($app, $container);
(new CacheRoutes())->register($app, $container);

// Run application
$app->run();
