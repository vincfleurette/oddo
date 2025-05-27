<?php

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
