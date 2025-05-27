<?php
// src/Config/AppConfig.php
namespace App\Config;

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
                "ttl" => 3600, // 1 hour
            ],
            "cache" => [
                "ttl" => 3600, // 1 hour
                "enabled" => true,
            ],
            "storage" => [
                "driver" => "file", // file, redis, database
                "path" => __DIR__ . "/../../storage",
                "prefix" => "oddo_",
            ],
        ];
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
}
