<?php

namespace App\Storage;

interface StorageDriverInterface
{
    public function set(string $key, array $data, ?int $ttl = null): bool;
    public function get(string $key): ?array;
    public function delete(string $key): bool;
    public function exists(string $key): bool;
    public function clear(string $pattern = "*"): bool;
    public function getSize(string $key): int;
}
