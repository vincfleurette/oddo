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
}
