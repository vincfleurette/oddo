<?php

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
