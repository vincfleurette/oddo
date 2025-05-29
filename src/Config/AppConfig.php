<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Configuration centralisée de l'application
 */
class AppConfig
{
    private array $config;

    public function __construct()
    {
        $this->config = [
            "oddo" => [
                "base_uri" => rtrim($_ENV["ODDO_BASE_URI"] ?? "", "/") . "/",
            ],
            "jwt" => [
                "secret" => $_ENV["JWT_SECRET"] ?? "",
                "ttl" => (int) ($_ENV["JWT_TTL"] ?? 3600),
            ],
            "cache" => [
                "ttl" => (int) ($_ENV["CACHE_TTL"] ?? 3600),
                "enabled" => filter_var(
                    $_ENV["CACHE_ENABLED"] ?? "true",
                    FILTER_VALIDATE_BOOLEAN
                ),
            ],
            "storage" => [
                "driver" => $_ENV["STORAGE_DRIVER"] ?? "file",
                "path" => $_ENV["STORAGE_PATH"] ?? __DIR__ . "/../../storage",
                "prefix" => $_ENV["STORAGE_PREFIX"] ?? "oddo_",
                // Redis config
                "host" => $_ENV["REDIS_HOST"] ?? "127.0.0.1",
                "port" => (int) ($_ENV["REDIS_PORT"] ?? 6379),
                "password" => $_ENV["REDIS_PASSWORD"] ?? "",
                "database" => (int) ($_ENV["REDIS_DATABASE"] ?? 0),
                // Database config
                "dsn" => $_ENV["DATABASE_DSN"] ?? "",
                "username" => $_ENV["DATABASE_USERNAME"] ?? "",
                "db_password" => $_ENV["DATABASE_PASSWORD"] ?? "",
            ],
            "app" => [
                "env" => $_ENV["APP_ENV"] ?? "production",
                "debug" => filter_var(
                    $_ENV["APP_DEBUG"] ?? "false",
                    FILTER_VALIDATE_BOOLEAN
                ),
            ],
        ];

        $this->validate();
    }

    public function getOddoConfig(): array
    {
        return $this->config["oddo"];
    }

    public function getJwtSecret(): string
    {
        return $this->config["jwt"]["secret"];
    }

    public function getJwtTtl(): int
    {
        return $this->config["jwt"]["ttl"];
    }

    public function getCacheTtl(): int
    {
        return $this->config["cache"]["ttl"];
    }

    public function isCacheEnabled(): bool
    {
        return $this->config["cache"]["enabled"];
    }

    public function getStorageConfig(): array
    {
        return $this->config["storage"];
    }

    public function getAppEnv(): string
    {
        return $this->config["app"]["env"];
    }

    public function isDebug(): bool
    {
        return $this->config["app"]["debug"];
    }

    public function get(string $key, $default = null)
    {
        $keys = explode(".", $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * Valide la configuration au démarrage
     */
    private function validate(): void
    {
        if (empty($this->config["jwt"]["secret"])) {
            throw new \InvalidArgumentException(
                "JWT_SECRET is required and cannot be empty"
            );
        }

        if (strlen($this->config["jwt"]["secret"]) < 32) {
            throw new \InvalidArgumentException(
                "JWT_SECRET must be at least 32 characters long"
            );
        }

        if (empty($this->config["oddo"]["base_uri"])) {
            throw new \InvalidArgumentException(
                "ODDO_BASE_URI is required and cannot be empty"
            );
        }
    }
}
