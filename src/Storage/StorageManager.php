<?php

namespace App\Storage;

use App\Storage\Drivers\FileStorageDriver;
use App\Storage\Drivers\RedisStorageDriver;
use App\Storage\Drivers\DatabaseStorageDriver;

class StorageManager
{
    private StorageDriverInterface $driver;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->prefix = $config["prefix"] ?? "";
        $this->driver = $this->createDriver($config);
    }

    private function createDriver(array $config): StorageDriverInterface
    {
        switch ($config["driver"]) {
            case "redis":
                return new RedisStorageDriver($config);
            case "database":
                return new DatabaseStorageDriver($config);
            case "file":
            default:
                return new FileStorageDriver($config);
        }
    }

    public function store(string $key, array $data, ?int $ttl = null): bool
    {
        $payload = [
            "timestamp" => (new \DateTimeImmutable())->format("c"),
            "data" => $data,
            "ttl" => $ttl,
        ];

        return $this->driver->set($this->prefix . $key, $payload, $ttl);
    }

    public function retrieve(string $key): ?array
    {
        $payload = $this->driver->get($this->prefix . $key);

        if ($payload === null) {
            return null;
        }

        // Check TTL if specified
        if (isset($payload["ttl"]) && $payload["ttl"] > 0) {
            $timestamp = new \DateTimeImmutable($payload["timestamp"]);
            $now = new \DateTimeImmutable();

            if (
                $now->getTimestamp() - $timestamp->getTimestamp() >
                $payload["ttl"]
            ) {
                $this->delete($key);
                return null;
            }
        }

        return $payload["data"] ?? null;
    }

    public function delete(string $key): bool
    {
        return $this->driver->delete($this->prefix . $key);
    }

    public function exists(string $key): bool
    {
        return $this->driver->exists($this->prefix . $key);
    }

    public function clear(string $pattern = "*"): bool
    {
        return $this->driver->clear($this->prefix . $pattern);
    }

    public function getInfo(string $key): ?array
    {
        $payload = $this->driver->get($this->prefix . $key);

        if ($payload === null) {
            return null;
        }

        $timestamp = new \DateTimeImmutable($payload["timestamp"]);
        $now = new \DateTimeImmutable();
        $age = $now->getTimestamp() - $timestamp->getTimestamp();
        $ttl = $payload["ttl"] ?? 0;

        return [
            "key" => $key,
            "timestamp" => $payload["timestamp"],
            "age" => $age,
            "ageHuman" => $this->formatDuration($age),
            "ttl" => $ttl,
            "isExpired" => $ttl > 0 && $age > $ttl,
            "expiresIn" => $ttl > 0 ? max(0, $ttl - $age) : null,
            "expiresInHuman" =>
                $ttl > 0 ? $this->formatDuration(max(0, $ttl - $age)) : null,
            "size" => $this->driver->getSize($this->prefix . $key),
        ];
    }

    private function formatDuration(int $seconds): string
    {
        return gmdate("H:i:s", $seconds);
    }

    /**
     * Generate a user-specific cache key
     */
    public function getUserKey(string $userId, string $type): string
    {
        return "user_{$userId}_{$type}";
    }

    /**
     * Generate a global cache key
     */
    public function getGlobalKey(string $type): string
    {
        return "global_{$type}";
    }
}
