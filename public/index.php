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
        // Charger les claims JWT
        $jwt = $req->getAttribute("jwt");
        $oddoToken = $jwt->oddo;
        $oddoUuid = $jwt->uuid;

        // Injecter le token
        $api = new OddoApi("", "", $baseUri);
        $api->setToken($oddoToken);

        // N'injecte l'UUID que s'il existe
        if ($oddoUuid !== null) {
            $api->setUuid($oddoUuid);
        }

        // Tenter le cache
        if (file_exists($cachePath)) {
            $json = json_decode((string) file_get_contents($cachePath), true);
            if (isset($json["timestamp"], $json["data"])) {
                $ts = strtotime($json["timestamp"]);
                if (time() - $ts < $cacheTtl) {
                    // Retourner les données en cache avec stats
                    $cachedResponse = addPerformanceStats($json["data"]);
                    $res->getBody()->write(json_encode($cachedResponse));
                    return $res->withHeader("Content-Type", "application/json");
                }
            }
        }

        // Récupération fraîche
        $api = new OddoApi("", "", $baseUri);
        $api->setToken($oddoToken);
        $api->setUuid($oddoUuid);

        $service = new OddoApiService($api);
        $dtos = $service->fetchAccountsWithPositions();
        $accounts = array_map(
            fn(AccountWithPositionsDTO $dto) => $dto->toArray(),
            $dtos
        );

        // Sauvegarder en cache
        if (!is_dir(dirname($cachePath))) {
            mkdir(dirname($cachePath), 0755, true);
        }
        file_put_contents(
            $cachePath,
            json_encode(
                ["timestamp" => date("c"), "data" => $accounts],
                JSON_PRETTY_PRINT
            )
        );

        // Ajouter les statistiques de performance
        $responseWithStats = addPerformanceStats($accounts);

        $res->getBody()->write(json_encode($responseWithStats));
        return $res->withHeader("Content-Type", "application/json");
    });
})->add($jwtMiddleware);

// Route pour invalider le cache
$app->delete("/cache", function (Request $req, Response $res) use ($cachePath) {
    $deleted = false;
    $message = "";

    if (file_exists($cachePath)) {
        if (unlink($cachePath)) {
            $deleted = true;
            $message = "Cache invalidated successfully";
        } else {
            $message = "Failed to delete cache file";
        }
    } else {
        $message = "Cache file does not exist";
    }

    $response = [
        "success" => $deleted,
        "message" => $message,
        "cachePath" => $cachePath,
        "timestamp" => date("c"),
    ];

    $res->getBody()->write(json_encode($response));
    return $res->withHeader("Content-Type", "application/json");
})->add($jwtMiddleware);

// Route pour obtenir les informations du cache
$app->get("/cache/info", function (Request $req, Response $res) use (
    $cachePath,
    $cacheTtl
) {
    $info = [
        "cachePath" => $cachePath,
        "cacheExists" => file_exists($cachePath),
        "cacheTtl" => $cacheTtl,
        "cacheTtlHuman" => gmdate("H:i:s", $cacheTtl),
    ];

    if (file_exists($cachePath)) {
        $json = json_decode((string) file_get_contents($cachePath), true);
        if (isset($json["timestamp"])) {
            $cacheTimestamp = strtotime($json["timestamp"]);
            $age = time() - $cacheTimestamp;
            $isExpired = $age >= $cacheTtl;

            $info["cacheTimestamp"] = $json["timestamp"];
            $info["cacheAge"] = $age;
            $info["cacheAgeHuman"] = gmdate("H:i:s", $age);
            $info["isExpired"] = $isExpired;
            $info["expiresIn"] = max(0, $cacheTtl - $age);
            $info["expiresInHuman"] = gmdate("H:i:s", max(0, $cacheTtl - $age));
            $info["accountsCount"] = count($json["data"] ?? []);
        }

        $info["fileSizeBytes"] = filesize($cachePath);
        $info["fileSizeHuman"] = formatBytes(filesize($cachePath));
    }

    $res->getBody()->write(json_encode($info, JSON_PRETTY_PRINT));
    return $res->withHeader("Content-Type", "application/json");
})->add($jwtMiddleware);

// Route pour forcer le refresh du cache
$app->post("/cache/refresh", function (Request $req, Response $res) use (
    $baseUri,
    $cachePath,
    $cacheTtl
) {
    // Charger les claims JWT
    $jwt = $req->getAttribute("jwt");
    $oddoToken = $jwt->oddo;
    $oddoUuid = $jwt->uuid;

    // Supprimer l'ancien cache
    if (file_exists($cachePath)) {
        unlink($cachePath);
    }

    // Récupérer des données fraîches
    $api = new OddoApi("", "", $baseUri);
    $api->setToken($oddoToken);
    if ($oddoUuid !== null) {
        $api->setUuid($oddoUuid);
    }

    $service = new OddoApiService($api);
    $dtos = $service->fetchAccountsWithPositions();
    $data = array_map(
        fn(AccountWithPositionsDTO $dto) => $dto->toArray(),
        $dtos
    );

    // Sauvegarder le nouveau cache
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

    $response = [
        "success" => true,
        "message" => "Cache refreshed successfully",
        "accountsCount" => count($data),
        "timestamp" => date("c"),
        "cachePath" => $cachePath,
    ];

    $res->getBody()->write(json_encode($response));
    return $res->withHeader("Content-Type", "application/json");
})->add($jwtMiddleware);

// Fonction utilitaire pour formater les tailles de fichier
function formatBytes($bytes, $precision = 2)
{
    $units = ["B", "KB", "MB", "GB", "TB"];

    for ($i = 0; $bytes > 1024; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . " " . $units[$i];
}

/**
 * Ajoute les statistiques de performance aux données des comptes
 */
function addPerformanceStats(array $accounts): array
{
    $stats = [
        "totalValue" => 0,
        "totalPMVL" => 0,
        "totalPMVR" => 0,
        "weightedPerformance" => 0,
        "totalWeight" => 0,
        "positionsCount" => 0,
        "accountsCount" => count($accounts),
        "performanceByAssetClass" => [],
        "topPerformers" => [],
        "worstPerformers" => [],
        "lastUpdate" => date("c"),
    ];

    $allPositions = [];

    // Calculer les statistiques pour chaque compte
    foreach ($accounts as &$account) {
        $accountStats = [
            "totalPMVL" => 0,
            "weightedPerformance" => 0,
            "totalWeight" => 0,
            "positionsCount" => count($account["positions"]),
        ];

        $stats["totalValue"] += $account["value"];

        foreach ($account["positions"] as $position) {
            $stats["positionsCount"]++;
            $pmvl = $position["pmvl"] ?? 0;
            $pmvr = $position["pmvr"] ?? 0;
            $weight = $position["weightMinute"] ?? 0;

            // CORRECTION: utiliser 'perf' au lieu de 'performance'
            $performance = $position["perf"] ?? 0;

            // Stats globales
            $stats["totalPMVL"] += $pmvl;
            $stats["totalPMVR"] += $pmvr;
            $stats["weightedPerformance"] += $performance * $weight;
            $stats["totalWeight"] += $weight;

            // Stats par compte
            $accountStats["totalPMVL"] += $pmvl;
            $accountStats["weightedPerformance"] += $performance * $weight;
            $accountStats["totalWeight"] += $weight;

            // Performance par classe d'actif
            $assetClass = $position["classActif"] ?? "Unknown";
            if (!isset($stats["performanceByAssetClass"][$assetClass])) {
                $stats["performanceByAssetClass"][$assetClass] = [
                    "totalValue" => 0,
                    "totalWeight" => 0,
                    "weightedPerformance" => 0,
                    "positionsCount" => 0,
                ];
            }

            $stats["performanceByAssetClass"][$assetClass]["totalValue"] +=
                $position["valeurMarcheDeviseSecurite"] ?? 0;
            $stats["performanceByAssetClass"][$assetClass][
                "totalWeight"
            ] += $weight;
            $stats["performanceByAssetClass"][$assetClass][
                "weightedPerformance"
            ] += $performance * $weight;
            $stats["performanceByAssetClass"][$assetClass]["positionsCount"]++;

            // Stocker pour les top/worst performers
            $allPositions[] = array_merge($position, [
                "accountNumber" => $account["accountNumber"],
                "performance" => $performance, // Normaliser le nom du champ
            ]);
        }

        // Calculer la performance moyenne pondérée du compte
        if ($accountStats["totalWeight"] > 0) {
            $accountStats["weightedPerformance"] =
                $accountStats["weightedPerformance"] /
                $accountStats["totalWeight"];
        }

        // Ajouter les stats au compte
        $account["stats"] = array_merge($accountStats, [
            "formatted" => [
                "totalPMVL" => sprintf("%+.2f €", $accountStats["totalPMVL"]),
                "weightedPerformance" => sprintf(
                    "%+.2f%%",
                    $accountStats["weightedPerformance"]
                ),
                "pmvlColor" =>
                    $accountStats["totalPMVL"] >= 0 ? "green" : "red",
                "performanceColor" =>
                    $accountStats["weightedPerformance"] >= 0 ? "green" : "red",
            ],
        ]);
    }

    // Calculer la performance moyenne pondérée globale
    if ($stats["totalWeight"] > 0) {
        $stats["weightedPerformance"] =
            $stats["weightedPerformance"] / $stats["totalWeight"];
    }

    // Calculer la performance moyenne par classe d'actif
    foreach ($stats["performanceByAssetClass"] as $class => &$classStats) {
        if ($classStats["totalWeight"] > 0) {
            $classStats["averagePerformance"] =
                $classStats["weightedPerformance"] / $classStats["totalWeight"];
        } else {
            $classStats["averagePerformance"] = 0;
        }

        // Ajouter le formatage
        $classStats["formatted"] = [
            "averagePerformance" => sprintf(
                "%+.2f%%",
                $classStats["averagePerformance"]
            ),
            "totalValue" => number_format($classStats["totalValue"], 2) . " €",
            "performanceColor" =>
                $classStats["averagePerformance"] >= 0 ? "green" : "red",
        ];
    }

    // Trier les positions par performance
    usort($allPositions, function ($a, $b) {
        $perfA = $a["performance"] ?? 0;
        $perfB = $b["performance"] ?? 0;
        return $perfB <=> $perfA;
    });

    // Top 5 performers
    $stats["topPerformers"] = array_slice(
        array_map(function ($pos) {
            $performance = $pos["performance"] ?? 0;
            return [
                "isinCode" => $pos["isinCode"] ?? "",
                "libInstrument" => $pos["libInstrument"] ?? "",
                "performance" => $performance,
                "valeurMarcheDeviseSecurite" =>
                    $pos["valeurMarcheDeviseSecurite"] ?? 0,
                "weightMinute" => $pos["weightMinute"] ?? 0,
                "accountNumber" => $pos["accountNumber"] ?? "",
                "classActif" => $pos["classActif"] ?? "",
                "formatted" => [
                    "performance" => sprintf("%+.2f%%", $performance),
                    "value" =>
                        number_format(
                            $pos["valeurMarcheDeviseSecurite"] ?? 0,
                            2
                        ) . " €",
                    "weight" => sprintf("%.1f%%", $pos["weightMinute"] ?? 0),
                    "performanceColor" => $performance >= 0 ? "green" : "red",
                ],
            ];
        }, $allPositions),
        0,
        5
    );

    // Worst 5 performers
    $stats["worstPerformers"] = array_slice(
        array_map(function ($pos) {
            $performance = $pos["performance"] ?? 0;
            return [
                "isinCode" => $pos["isinCode"] ?? "",
                "libInstrument" => $pos["libInstrument"] ?? "",
                "performance" => $performance,
                "valeurMarcheDeviseSecurite" =>
                    $pos["valeurMarcheDeviseSecurite"] ?? 0,
                "weightMinute" => $pos["weightMinute"] ?? 0,
                "accountNumber" => $pos["accountNumber"] ?? "",
                "classActif" => $pos["classActif"] ?? "",
                "formatted" => [
                    "performance" => sprintf("%+.2f%%", $performance),
                    "value" =>
                        number_format(
                            $pos["valeurMarcheDeviseSecurite"] ?? 0,
                            2
                        ) . " €",
                    "weight" => sprintf("%.1f%%", $pos["weightMinute"] ?? 0),
                    "performanceColor" => $performance >= 0 ? "green" : "red",
                ],
            ];
        }, array_reverse($allPositions)),
        0,
        5
    );

    // Ajouter des métadonnées formatées
    $stats["formatted"] = [
        "totalValue" => number_format($stats["totalValue"], 2) . " €",
        "totalPMVL" => sprintf("%+.2f €", $stats["totalPMVL"]),
        "weightedPerformance" => sprintf(
            "%+.2f%%",
            $stats["weightedPerformance"]
        ),
        "pmvlColor" => $stats["totalPMVL"] >= 0 ? "green" : "red",
        "performanceColor" =>
            $stats["weightedPerformance"] >= 0 ? "green" : "red",
    ];

    return [
        "accounts" => $accounts,
        "portfolio" => $stats,
    ];
}
$app->run();
