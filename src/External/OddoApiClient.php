<?php

declare(strict_types=1);

namespace App\External;

use GuzzleHttp\Client;
use App\DTO\AccountDTO;

/**
 * Client API Oddo avec support mode mock pour développement
 */
class OddoApiClient implements OddoApiClientInterface
{
    private ?Client $client = null;
    private ?string $token = null;
    private ?string $uuid = null;
    private array $config;
    private bool $mockMode = false;

    public function __construct(array $config)
    {
        $this->config = $config;

        // Activer le mode mock si l'URL contient "your-oddo-api-url" (URL d'exemple)
        if (strpos($config["base_uri"], "your-oddo-api-url") !== false) {
            $this->mockMode = true;
        } else {
            $this->client = new Client([
                "base_uri" => $config["base_uri"],
                "http_errors" => false,
                "timeout" => 30,
            ]);
        }
    }

    public function login(string $username, string $password): bool
    {
        if ($this->mockMode) {
            return $this->mockLogin($username, $password);
        }

        try {
            $response = $this->client->post("core/Login", [
                "json" => [
                    "UserName" => $username,
                    "Password" => $password,
                    "SmsCode" => null,
                    "culture" => "fr-FR",
                ],
            ]);

            if ($response->getStatusCode() !== 200) {
                return false;
            }

            $data = json_decode((string) $response->getBody(), true);
            $this->token = $data["token"] ?? null;
            $this->uuid = $response->getHeaderLine("X-UUID") ?: null;

            return (bool) $this->token;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function setUuid(?string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getAccounts(): array
    {
        if ($this->mockMode) {
            return $this->getMockAccounts();
        }

        $response = $this->request("POST", "accounts/FindLoginAccounts", [
            "CodeBureau" => "",
            "selectedFields" => [
                "valorisation",
                "performance",
                "especes",
                "securityAccountProviderLabel",
                "libelle",
                "CodFront",
            ],
            "culture" => "fr-FR",
        ]);

        if ($response === null) {
            throw new \RuntimeException("Failed to fetch accounts");
        }

        $items = $response["accountsTiers"]["principalsAccounts"] ?? [];

        return array_map(
            fn(array $item) => new AccountDTO([
                "accountNumber" => $item["accountNum"] ?? "",
                "label" => $item["libelle"] ?? "",
                "value" => floatval($item["valorisation"] ?? 0),
            ]),
            $items
        );
    }

    public function getPositions(
        string $accountNumber,
        ?string $arreteAu = null
    ): array {
        if ($this->mockMode) {
            return $this->getMockPositions($accountNumber);
        }

        if ($arreteAu === null) {
            $arreteAu = $this->getLastBusinessDay();
        }

        $params = [
            "i" => 0,
            "p" => 10,
            "sf" => "",
            "sd" => "",
            "CodeBureau" => "",
            "AccountNums" => [$accountNumber],
            "Type" => 3,
            "ArreteAu" => $arreteAu,
            "culture" => "fr-FR",
        ];

        $response = $this->request(
            "POST",
            "accounts/FindAccountsPositions",
            $params
        );

        if ($response === null) {
            throw new \RuntimeException(
                "Failed to fetch positions for account {$accountNumber}"
            );
        }

        return $response["values"] ?? [];
    }

    /**
     * Mock login pour le développement
     */
    private function mockLogin(string $username, string $password): bool
    {
        if ($username === "demo" && $password === "demo") {
            $this->token = "mock_token_" . time();
            $this->uuid = "mock_uuid_" . uniqid();
            return true;
        }
        return false;
    }

    /**
     * Comptes fictifs pour le développement
     */
    private function getMockAccounts(): array
    {
        return [
            new AccountDTO([
                "accountNumber" => "DEMO001",
                "label" => "Compte Démo 1",
                "value" => 25000.5,
            ]),
            new AccountDTO([
                "accountNumber" => "DEMO002",
                "label" => "Compte Démo 2",
                "value" => 15750.25,
            ]),
        ];
    }

    /**
     * Positions fictives pour le développement
     */
    private function getMockPositions(string $accountNumber): array
    {
        return [
            [
                "isinCode" => "FR0000120271",
                "libInstrument" => "Total SE",
                "valorisationAchatNette" => 5000.0,
                "valeurMarcheDeviseSecurite" => 5250.0,
                "dateArrete" => date("Y-m-d"),
                "quantityMinute" => 100.0,
                "pmvl" => 250.0,
                "pmvr" => 0.0,
                "weightMinute" => 25.0,
                "reportingAssetClassCode" => "EQUITY",
                "perf" => 5.0,
                "classActif" => "Actions",
                "closingPriceInListingCurrency" => 52.5,
            ],
            [
                "isinCode" => "FR0000131906",
                "libInstrument" => "LVMH",
                "valorisationAchatNette" => 3000.0,
                "valeurMarcheDeviseSecurite" => 3150.0,
                "dateArrete" => date("Y-m-d"),
                "quantityMinute" => 50.0,
                "pmvl" => 150.0,
                "pmvr" => 0.0,
                "weightMinute" => 15.0,
                "reportingAssetClassCode" => "EQUITY",
                "perf" => 5.0,
                "classActif" => "Actions",
                "closingPriceInListingCurrency" => 63.0,
            ],
        ];
    }

    private function getLastBusinessDay(): string
    {
        $date = new \DateTime("today");
        do {
            $date->modify("-1 day");
        } while (in_array((int) $date->format("N"), [6, 7]));

        return $date->format("Y-m-d");
    }

    private function request(
        string $method,
        string $endpoint,
        array $data = []
    ): ?array {
        if ($this->mockMode) {
            throw new \RuntimeException(
                "Mock mode: Real API calls not supported"
            );
        }

        if (!$this->token) {
            throw new \RuntimeException("No authentication token available");
        }

        try {
            $headers = [
                "X-Token" => $this->token,
                "Accept" => "application/json",
            ];

            if ($this->uuid) {
                $headers["X-UUID"] = $this->uuid;
            }

            $options = ["headers" => $headers];

            if (in_array(strtoupper($method), ["POST", "PUT", "PATCH"])) {
                $options["json"] = $data;
            } elseif ($method === "GET" && $data) {
                $endpoint .= "?" . http_build_query($data);
            }

            $response = $this->client->request($method, $endpoint, $options);

            if ($response->getStatusCode() === 401) {
                throw new \RuntimeException("Authentication token expired");
            }

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $result = json_decode((string) $response->getBody(), true);

            // Rafraîchir les tokens si présents
            if (isset($result["token"])) {
                $this->token = $result["token"];
            }

            $newUuid = $response->getHeaderLine("X-UUID");
            if ($newUuid) {
                $this->uuid = $newUuid;
            }

            return $result;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            throw new \RuntimeException(
                "API request failed: " . $e->getMessage()
            );
        }
    }
}
