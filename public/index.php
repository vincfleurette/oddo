<?php

ini_set("display_errors", 1);
ini_set("display_startup_errors", 1);
error_reporting(E_ALL);

require __DIR__ . "/../vendor/autoload.php";

use Dotenv\Dotenv;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response as SlimResponse;
use App\OddoApi;
use App\OddoApiService;
use App\DTO\AccountWithPositionsDTO;
use Slim\Exception\HttpNotFoundException;
use Slim\Middleware\ErrorMiddleware;
// Load environment
$dotenv = Dotenv::createImmutable(__DIR__ . "/../");
$dotenv->safeLoad();

$baseUri = rtrim($_ENV["ODDO_BASE_URI"], "/") . "/";
$jwtSecret = $_ENV["JWT_SECRET"];

// Cache settings
$cachePath = __DIR__ . "/../storage/accounts_with_positions.json";
$cacheTtl = 3600; // 1 hour

$app = AppFactory::create();
// Affiche les détails des erreurs et convertit les exceptions en réponse JSON
/** @var \Slim\App $app */
$errorMiddleware = new ErrorMiddleware(
    $app->getCallableResolver(),
    $app->getResponseFactory(),
    false, // displayErrorDetails (désactivé en prod)
    true, // logErrors
    true // logErrorDetails
);
// handler 404 → JSON
$errorMiddleware->setErrorHandler(HttpNotFoundException::class, function (
    Psr\Http\Message\ServerRequestInterface $req,
    Throwable $e
) use ($app) {
    $resp = $app->getResponseFactory()->createResponse(404);
    $resp->getBody()->write(json_encode(["error" => "Not Found"]));
    return $resp->withHeader("Content-Type", "application/json");
});
$app->add($errorMiddleware);
// Strip trailing slash
$app->add(function (
    Request $request,
    RequestHandlerInterface $handler
): Response {
    $uri = $request->getUri();
    $path = $uri->getPath();
    if ($path !== "/" && str_ends_with($path, "/")) {
        $newUri = $uri->withPath(rtrim($path, "/"));
        $response = new SlimResponse();
        return $response
            ->withHeader("Location", (string) $newUri)
            ->withStatus(301);
    }
    return $handler->handle($request);
});

// Public health check
$app->get("/status", function (Request $req, Response $res) {
    $res->getBody()->write(json_encode(["status" => "ok"]));
    return $res->withHeader("Content-Type", "application/json");
});

// Login route issues JWT
$app->post("/login", function (Request $req, Response $res) use (
    $baseUri,
    $jwtSecret
) {
    $body = json_decode((string) $req->getBody(), true);
    $user = $body["user"] ?? "";
    $pass = $body["pass"] ?? "";
    if (!$user || !$pass) {
        $res->getBody()->write(json_encode(["error" => "Missing credentials"]));
        return $res
            ->withStatus(400)
            ->withHeader("Content-Type", "application/json");
    }
    $api = new OddoApi($user, $pass, $baseUri);
    if (!$api->login()) {
        $res->getBody()->write(json_encode(["error" => "Invalid credentials"]));
        return $res
            ->withStatus(401)
            ->withHeader("Content-Type", "application/json");
    }
    $oddoToken = $api->getToken();
    $oddoUuid = $api->getUuid();
    $now = time();
    $exp = $now + 3600;
    $payload = [
        "iat" => $now,
        "exp" => $exp,
        "sub" => $user,
        "oddo" => $oddoToken,
        "uuid" => $oddoUuid,
    ];
    $jwt = JWT::encode($payload, $jwtSecret, "HS256");
    $res->getBody()->write(json_encode(["jwt" => $jwt]));
    return $res->withHeader("Content-Type", "application/json");
});

// JWT authentication middleware
$jwtMiddleware = function (Request $req, RequestHandlerInterface $handler) use (
    $jwtSecret
) {
    $auth = $req->getHeaderLine("Authorization");
    if (!preg_match('/Bearer\s+(.+)$/', $auth, $m)) {
        $resp = new SlimResponse();
        $resp
            ->getBody()
            ->write(json_encode(["error" => "Missing Bearer token"]));
        return $resp
            ->withStatus(401)
            ->withHeader("Content-Type", "application/json");
    }
    try {
        $token = JWT::decode($m[1], new Key($jwtSecret, "HS256"));
    } catch (Exception $e) {
        $resp = new SlimResponse();
        $resp->getBody()->write(json_encode(["error" => "Invalid token"]));
        return $resp
            ->withStatus(401)
            ->withHeader("Content-Type", "application/json");
    }
    return $handler->handle($req->withAttribute("jwt", $token));
};

// Protected routes
$app->group("", function ($group) use ($baseUri, $cachePath, $cacheTtl) {
    $group->get("/accounts", function (Request $req, Response $res) use (
        $baseUri,
        $cachePath,
        $cacheTtl
    ) {
        // load JWT claims
        $jwt = $req->getAttribute("jwt");
        $oddoToken = $jwt->oddo;
        $oddoUuid = $jwt->uuid;

        // injecter le token
        $api = new OddoApi("", "", $baseUri);
        $api->setToken($oddoToken);

        // n’injecte l’UUID que s’il existe
        if ($oddoUuid !== null) {
            $api->setUuid($oddoUuid);
        }

        // attempt cache
        if (file_exists($cachePath)) {
            $json = json_decode((string) file_get_contents($cachePath), true);
            if (isset($json["timestamp"], $json["data"])) {
                $ts = strtotime($json["timestamp"]);
                if (time() - $ts < $cacheTtl) {
                    $res->getBody()->write(json_encode($json["data"]));
                    return $res->withHeader("Content-Type", "application/json");
                }
            }
        }

        // fresh fetch
        $api = new OddoApi("", "", $baseUri);
        $api->setToken($oddoToken);
        $api->setUuid($oddoUuid);

        $service = new OddoApiService($api);
        $dtos = $service->fetchAccountsWithPositions();
        $data = array_map(
            fn(AccountWithPositionsDTO $dto) => $dto->toArray(),
            $dtos
        );

        if (!is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), 0755, true);
        }
        file_put_contents(
            $cachePath,
            json_encode(
                ["timestamp" => date("c"), "data" => $data],
                JSON_PRETTY_PRINT
            )
        );

        $res->getBody()->write(json_encode($data));
        return $res->withHeader("Content-Type", "application/json");
    });
})->add($jwtMiddleware);

$app->run();
