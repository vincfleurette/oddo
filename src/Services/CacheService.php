<?php

/**
 * Service de cache intelligent
 */
namespace App\Services;

use App\Storage\StorageManager;

class CacheService
{
    private StorageManager $storage;
    private int $defaultTtl;

    public function __construct(StorageManager $storage, int $defaultTtl = 3600)
    {
        $this->storage = $storage;
        $this->defaultTtl = $defaultTtl;
    }

    public function getUserAccounts(string $userId): ?array
    {
        $key = $this->storage->getUserKey($userId, "accounts");
        return $this->storage->retrieve($key);
    }

    public function setUserAccounts(
        string $userId,
        array $accounts,
        ?int $ttl = null
    ): bool {
        $key = $this->storage->getUserKey($userId, "accounts");
        return $this->storage->store(
            $key,
            $accounts,
            $ttl ?? $this->defaultTtl
        );
    }

    public function invalidateUser(string $userId): bool
    {
        $pattern = "user_{$userId}_*";
        return $this->storage->clear($pattern);
    }

    public function invalidateAll(): bool
    {
        return $this->storage->clear();
    }

    public function getCacheInfo(string $userId, string $type): ?array
    {
        $key = $this->storage->getUserKey($userId, $type);
        return $this->storage->getInfo($key);
    }

    public function refreshUserAccounts(
        string $userId,
        callable $dataFetcher,
        ?int $ttl = null
    ): array {
        // Supprimer l'ancien cache
        $key = $this->storage->getUserKey($userId, "accounts");
        $this->storage->delete($key);

        // Récupérer de nouvelles données
        $data = $dataFetcher();

        // Sauvegarder en cache
        $this->setUserAccounts($userId, $data, $ttl);

        return $data;
    }

    public function getDetailedCacheInfo(string $userId): array
    {
        $accountsKey = $this->storage->getUserKey($userId, "accounts");
        $accountsInfo = $this->storage->getInfo($accountsKey);

        return [
            "cachePath" => "user_cache_{$userId}",
            "cacheExists" => $accountsInfo !== null,
            "cacheTtl" => $this->defaultTtl,
            "cacheTtlHuman" => $this->formatDuration($this->defaultTtl),
            "cacheTimestamp" => $accountsInfo["timestamp"] ?? null,
            "cacheAge" => $accountsInfo["age"] ?? null,
            "cacheAgeHuman" => $accountsInfo["ageHuman"] ?? null,
            "isExpired" => $accountsInfo["isExpired"] ?? false,
            "expiresIn" => $accountsInfo["expiresIn"] ?? null,
            "expiresInHuman" => $accountsInfo["expiresInHuman"] ?? null,
            "accountsCount" => $accountsInfo
                ? count($this->getUserAccounts($userId) ?? [])
                : 0,
            "fileSizeBytes" => $accountsInfo["size"] ?? 0,
            "fileSizeHuman" => $this->formatFileSize(
                $accountsInfo["size"] ?? 0
            ),
        ];
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ["B", "KB", "MB", "GB"];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1 << 10 * $pow;

        return round($bytes, 2) . " " . $units[$pow];
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return "{$seconds}s";
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . "m " . $seconds % 60 . "s";
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return "{$hours}h {$minutes}m";
    }
}
