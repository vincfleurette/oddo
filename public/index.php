<?php

ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require __DIR__ . "/../vendor/autoload.php";

use Dotenv\Dotenv;
use Slim\Factory\AppFactory;
use DI\Container;
use App\Config\AppConfig;
use App\Middleware\JwtMiddleware;
use App\Middleware\ErrorHandlerMiddleware;
use App\Routes\AuthRoutes;
use App\Routes\AccountRoutes;
use App\Routes\CacheRoutes;
use App\Routes\HealthRoutes;

// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->safeLoad();

// Create DI container
$container = new Container();

// Load configuration
$config = new AppConfig();
$container->set(AppConfig::class, $config);

// Register services
$container->set(\App\Services\OddoApiService::class, function ($container) use (
    $config
) {
    return new \App\Services\OddoApiService(
        new \App\External\OddoApiClient($config->getOddoConfig())
    );
});

$container->set(\App\Services\AuthService::class, function ($container) use (
    $config
) {
    return new \App\Services\AuthService(
        $container->get(\App\External\OddoApiClient::class),
        $config->getJwtSecret()
    );
});

$container->set(\App\Services\CacheService::class, function ($container) use (
    $config
) {
    return new \App\Services\CacheService(
        $container->get(\App\Storage\StorageManager::class),
        $config->getCacheTtl()
    );
});

$container->set(\App\Services\PortfolioService::class, function ($container) {
    return new \App\Services\PortfolioService();
});

$container->set(\App\Storage\StorageManager::class, function ($container) use (
    $config
) {
    return new \App\Storage\StorageManager($config->getStorageConfig());
});

// Create app with container
AppFactory::setContainer($container);
$app = AppFactory::create();

// Add middlewares
$app->add(new ErrorHandlerMiddleware());
$app->add(function ($request, $handler) {
    // Strip trailing slash middleware
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

$app->run();
