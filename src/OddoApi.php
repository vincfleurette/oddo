<?php

namespace App;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Handles authentication and HTTP requests to the Oddo BHF API.
 */
class OddoApi
{
    private Client $client;
    private string $username;
    private string $password;
    private ?string $token = null;
    private ?string $uuid = null;

    /**
     * @param string $username
     * @param string $password
     * @param string $baseUri
     */
    public function __construct(
        string $username,
        string $password,
        string $baseUri
    ) {
        $this->username = $username;
        $this->password = $password;
        $this->client = new Client([
            "base_uri" => $baseUri,
            "http_errors" => false,
        ]);
    }

    /**
     * Perform login, store token & uuid.
     */
    public function login(): bool
    {
        $resp = $this->client->post("core/Login", [
            "json" => [
                "UserName" => $this->username,
                "Password" => $this->password,
                "SmsCode" => null,
                "culture" => "fr-FR",
            ],
        ]);
        if ($resp->getStatusCode() !== 200) {
            return false;
        }
        $data = json_decode((string) $resp->getBody(), true);
        $this->token = $data["token"] ?? null;
        $this->uuid = $resp->getHeaderLine("X-UUID") ?: null;
        return (bool) $this->token;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }
    public function getUuid(): ?string
    {
        return $this->uuid;
    }
    public function setToken(string $t): void
    {
        $this->token = $t;
    }
    /**
     * Injecte l'UUID (peut être null si non renvoyé par l'API).
     */
    public function setUuid(?string $u): void
    {
        $this->uuid = $u;
    }

    /**
     * Generic API request with auto-login and retry on 401.
     *
     * @throws \RuntimeException If authentication fails.
     */
    public function request(
        string $method,
        string $endpoint,
        array $data = []
    ): ?array {
        // 1) Auto-login si pas de token
        if (!$this->token) {
            if (!$this->login()) {
                throw new \RuntimeException("Login failed");
            }
        }

        // 2) Prépare l'appel
        $call = function () use ($method, $endpoint, $data) {
            // Entêtes de base
            $headers = [
                "X-Token" => $this->token,
                "Accept" => "application/json",
            ];
            // X-UUID uniquement si défini
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

        // 3) Exécution + retry sur 401
        try {
            $response = $call();
            if ($response->getStatusCode() === 401) {
                // token expiré → relogin
                if (!$this->login()) {
                    return null;
                }
                $response = $call();
            }
            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $result = json_decode((string) $response->getBody(), true);

            // Rafraîchir token si présent
            if (isset($result["token"])) {
                $this->token = $result["token"];
            }
            // Rafraîchir uuid si présent
            $newUuid = $response->getHeaderLine("X-UUID");
            if ($newUuid) {
                $this->uuid = $newUuid;
            }

            return $result;
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            return null;
        }
    }
}
