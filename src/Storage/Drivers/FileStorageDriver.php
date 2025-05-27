<?php
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
