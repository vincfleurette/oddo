<?php

declare(strict_types=1);

namespace App\Services;

use App\External\OddoApiClientInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Service d'authentification JWT avec API Oddo
 */
class AuthService
{
    private OddoApiClientInterface $apiClient;
    private string $jwtSecret;
    private int $jwtTtl;

    public function __construct(
        OddoApiClientInterface $apiClient,
        string $jwtSecret,
        int $jwtTtl = 3600
    ) {
        $this->apiClient = $apiClient;
        $this->jwtSecret = $jwtSecret;
        $this->jwtTtl = $jwtTtl;
    }

    /**
     * Authentifie un utilisateur et retourne un JWT
     */
    public function authenticate(string $username, string $password): ?string
    {
        if (!$this->apiClient->login($username, $password)) {
            return null;
        }

        $token = $this->apiClient->getToken();
        $uuid = $this->apiClient->getUuid();

        $payload = [
            "iat" => time(),
            "exp" => time() + $this->jwtTtl,
            "sub" => $username,
            "oddo" => $token,
            "uuid" => $uuid,
        ];

        return JWT::encode($payload, $this->jwtSecret, "HS256");
    }

    /**
     * Valide un token JWT
     */
    public function validateToken(string $jwt): ?object
    {
        try {
            return JWT::decode($jwt, new Key($this->jwtSecret, "HS256"));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Extrait les informations d'authentification depuis un JWT
     */
    public function extractCredentials(object $jwtPayload): array
    {
        return [
            "username" => $jwtPayload->sub ?? "",
            "token" => $jwtPayload->oddo ?? "",
            "uuid" => $jwtPayload->uuid ?? null,
        ];
    }
}
