<?php
// Fix 1: src/Storage/Drivers/FileStorageDriver.php (UPDATED)

namespace App\Storage\Drivers;

use App\Storage\StorageDriverInterface;

class FileStorageDriver implements StorageDriverInterface
{
    private string $path;

    public function __construct(array $config)
    {
        $this->path = $config["path"] ?? __DIR__ . "/../../../storage";

        // Vérifier si le dossier existe et est accessible en écriture
        if (!$this->ensureStorageDirectory()) {
            // Fallback vers /tmp si le dossier principal n'est pas accessible
            $this->path = sys_get_temp_dir() . "/oddo_cache";
            $this->ensureStorageDirectory();
        }
    }

    private function ensureStorageDirectory(): bool
    {
        if (!is_dir($this->path)) {
            if (!@mkdir($this->path, 0755, true)) {
                error_log("Failed to create storage directory: {$this->path}");
                return false;
            }
        }

        if (!is_writable($this->path)) {
            error_log("Storage directory not writable: {$this->path}");
            return false;
        }

        return true;
    }

    public function set(string $key, array $data, ?int $ttl = null): bool
    {
        $filePath = $this->getFilePath($key);
        $dir = dirname($filePath);

        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true)) {
                error_log("Failed to create directory: {$dir}");
                return false;
            }
        }

        $result = @file_put_contents(
            $filePath,
            json_encode($data, JSON_PRETTY_PRINT),
            LOCK_EX
        );

        if ($result === false) {
            error_log("Failed to write cache file: {$filePath}");
            return false;
        }

        return true;
    }

    public function get(string $key): ?array
    {
        $filePath = $this->getFilePath($key);

        if (!file_exists($filePath)) {
            return null;
        }

        $content = @file_get_contents($filePath);
        if ($content === false) {
            error_log("Failed to read cache file: {$filePath}");
            return null;
        }

        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Invalid JSON in cache file: {$filePath}");
            return null;
        }

        return $data;
    }

    public function delete(string $key): bool
    {
        $filePath = $this->getFilePath($key);

        if (file_exists($filePath)) {
            return @unlink($filePath);
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
            if (!@unlink($file)) {
                $success = false;
                error_log("Failed to delete cache file: {$file}");
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

    /**
     * Get storage info for debugging
     */
    public function getStorageInfo(): array
    {
        return [
            "path" => $this->path,
            "exists" => is_dir($this->path),
            "writable" => is_writable($this->path),
            "permissions" => is_dir($this->path)
                ? substr(sprintf("%o", fileperms($this->path)), -4)
                : null,
            "free_space" => disk_free_space($this->path),
            "total_space" => disk_total_space($this->path),
        ];
    }
}
