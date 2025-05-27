<?php

/**
 * Client API Oddo refactorisé
 */
namespace App\External;

use GuzzleHttp\Client;
use App\DTO\AccountDTO;

class OddoApiClient implements OddoApiClientInterface
{
    private Client $client;
    private ?string $token = null;
    private ?string $uuid = null;
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->client = new Client([
            "base_uri" => $config["base_uri"],
            "http_errors" => false,
            "timeout" => 30,
        ]);
    }

    public function login(string $username, string $password): bool
    {
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

    private function getLastBusinessDay(): string
    {
        $date = new \DateTime("today");
        do {
            $date->modify("-1 day");
        } while (in_array((int) $date->format("N"), [6, 7])); // Skip weekends

        return $date->format("Y-m-d");
    }

    private function request(
        string $method,
        string $endpoint,
        array $data = []
    ): ?array {
        // Auto-login si pas de token
        if (!$this->token) {
            throw new \RuntimeException("No authentication token available");
        }

        $call = function () use ($method, $endpoint, $data) {
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

            return $this->client->request($method, $endpoint, $options);
        };

        try {
            $response = $call();

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
