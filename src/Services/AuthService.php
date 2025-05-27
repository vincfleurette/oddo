<?php

/**
 * Service d'authentification
 */
namespace App\Services;

use App\External\OddoApiClientInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthService
{
    private OddoApiClientInterface $apiClient;
    private string $jwtSecret;

    public function __construct(
        OddoApiClientInterface $apiClient,
        string $jwtSecret
    ) {
        $this->apiClient = $apiClient;
        $this->jwtSecret = $jwtSecret;
    }

    public function authenticate(string $username, string $password): ?string
    {
        if (!$this->apiClient->login($username, $password)) {
            return null;
        }

        $token = $this->apiClient->getToken();
        $uuid = $this->apiClient->getUuid();

        $payload = [
            "iat" => time(),
            "exp" => time() + 3600,
            "sub" => $username,
            "oddo" => $token,
            "uuid" => $uuid,
        ];

        return JWT::encode($payload, $this->jwtSecret, "HS256");
    }

    public function validateToken(string $jwt): ?object
    {
        try {
            return JWT::decode($jwt, new Key($this->jwtSecret, "HS256"));
        } catch (\Exception $e) {
            return null;
        }
    }

    public function extractCredentials(object $jwtPayload): array
    {
        return [
            "username" => $jwtPayload->sub ?? "",
            "token" => $jwtPayload->oddo ?? "",
            "uuid" => $jwtPayload->uuid ?? null,
        ];
    }
}
