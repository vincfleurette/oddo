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

// Storage Driver Interface
interface StorageDriverInterface
{
    public function set(string $key, array $data, ?int $ttl = null): bool;
    public function get(string $key): ?array;
    public function delete(string $key): bool;
    public function exists(string $key): bool;
    public function clear(string $pattern = "*"): bool;
    public function getSize(string $key): int;
}

// File Storage Driver
namespace App\Storage\Drivers;

use App\Storage\StorageDriverInterface;

class FileStorageDriver implements StorageDriverInterface
{
    private string $path;

    public function __construct(array $config)
    {
        $this->path = $config["path"] ?? __DIR__ . "/../../../storage";

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    public function set(string $key, array $data, ?int $ttl = null): bool
    {
        $filePath = $this->getFilePath($key);
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return file_put_contents(
            $filePath,
            json_encode($data, JSON_PRETTY_PRINT)
        ) !== false;
    }

    public function get(string $key): ?array
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = file_get_contents($filePath);
        return json_decode($content, true);
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            return unlink($filePath);
        }

        return true;
    }

    public function exists(string $key): bool
    {
        return file_exists($this->getFilePath($key));
    }

    public function clear(string $pattern = "*"): bool
    {
        $files = glob($this->path . "/" . str_replace("*", "*.json", $pattern));
        $success = true;

        foreach ($files as $file) {
            if (!unlink($file)) {
                $success = false;
            }
        }

        return $success;
    }

    public function getSize(string $key): int
    {
        $filePath = $this->getFilePath($key);
        return file_exists($filePath) ? filesize($filePath) : 0;
    }

    private function getFilePath(string $key): string
    {
        return $this->path .
            "/" .
            str_replace(["/", "\\"], "_", $key) .
            ".json";
    }
}

// Redis Storage Driver
class RedisStorageDriver implements StorageDriverInterface
{
    private \Redis $redis;

    public function __construct(array $config)
    {
        $this->redis = new \Redis();
        $this->redis->connect(
            $config["host"] ?? "127.0.0.1",
            $config["port"] ?? 6379
        );

        if (!empty($config["password"])) {
            $this->redis->auth($config["password"]);
        }

        if (!empty($config["database"])) {
            $this->redis->select($config["database"]);
        }
    }

    public function set(string $key, array $data, ?int $ttl = null): bool
    {
        $result = $this->redis->set($key, json_encode($data));

        if ($result && $ttl > 0) {
            $this->redis->expire($key, $ttl);
        }

        return $result;
    }

    public function get(string $key): ?array
    {
        $data = $this->redis->get($key);
        return $data ? json_decode($data, true) : null;
    }

    public function delete(string $key): bool
    {
        return $this->redis->del($key) > 0;
    }

    public function exists(string $key): bool
    {
        return $this->redis->exists($key) > 0;
    }

    public function clear(string $pattern = "*"): bool
    {
        $keys = $this->redis->keys($pattern);
        return empty($keys) || $this->redis->del($keys) > 0;
    }

    public function getSize(string $key): int
    {
        return strlen($this->redis->get($key) ?? "");
    }
}

// Database Storage Driver
class DatabaseStorageDriver implements StorageDriverInterface
{
    private \PDO $pdo;

    public function __construct(array $config)
    {
        $dsn = sprintf(
            "%s:host=%s;port=%s;dbname=%s",
            $config["driver"] ?? "mysql",
            $config["host"] ?? "127.0.0.1",
            $config["port"] ?? 3306,
            $config["database"] ?? "oddo"
        );

        $this->pdo = new \PDO($dsn, $config["username"], $config["password"]);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->createTable();
    }

    private function createTable(): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS cache_storage (
                cache_key VARCHAR(255) PRIMARY KEY,
                data LONGTEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                expires_at TIMESTAMP NULL
            )
        ";

        $this->pdo->exec($sql);
    }

    public function set(string $key, array $data, ?int $ttl = null): bool
    {
        $expiresAt = $ttl > 0 ? date("Y-m-d H:i:s", time() + $ttl) : null;

        $sql = "
            INSERT INTO cache_storage (cache_key, data, expires_at) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            data = VALUES(data), 
            expires_at = VALUES(expires_at),
            updated_at = CURRENT_TIMESTAMP
        ";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$key, json_encode($data), $expiresAt]);
    }

    public function get(string $key): ?array
    {
        $sql = "
            SELECT data FROM cache_storage 
            WHERE cache_key = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$key]);

        $result = $stmt->fetchColumn();
        return $result ? json_decode($result, true) : null;
    }

    public function delete(string $key): bool
    {
        $sql = "DELETE FROM cache_storage WHERE cache_key = ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$key]);
    }

    public function exists(string $key): bool
    {
        $sql = "
            SELECT 1 FROM cache_storage 
            WHERE cache_key = ? 
            AND (expires_at IS NULL OR expires_at > NOW())
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$key]);
        return $stmt->fetchColumn() !== false;
    }

    public function clear(string $pattern = "*"): bool
    {
        if ($pattern === "*") {
            $sql = "DELETE FROM cache_storage";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute();
        }

        $likePattern = str_replace("*", "%", $pattern);
        $sql = "DELETE FROM cache_storage WHERE cache_key LIKE ?";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$likePattern]);
    }

    public function getSize(string $key): int
    {
        $sql = "SELECT LENGTH(data) FROM cache_storage WHERE cache_key = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$key]);
        return (int) $stmt->fetchColumn();
    }
}
