<?php

namespace Erikwang2013\Consul\Config;

use Erikwang2013\Consul\Api\Kv;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

class ConfigCenter
{
    private Kv $kv;
    private ?CacheInterface $cache;
    private ?int $cacheTtl;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        Kv $kv,
        ?CacheInterface $cache = null,
        ?int $cacheTtl = 300,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->kv = $kv;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function get(string $key, $default = null): mixed
    {
        $cacheKey = "consul:config:{$key}";

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->kv->get($key);

        if ($result === null) {
            return $default;
        }

        $decoded = base64_decode($result['Value'] ?? '', true);
        $value = $decoded !== false ? $decoded : ($result['Value'] ?? $default);

        if ($this->cache) {
            $this->cache->set($cacheKey, $value, $this->cacheTtl);
        }

        return $value;
    }

    public function namespace(string $prefix): array
    {
        $cacheKey = "consul:config:ns:{$prefix}";

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->kv->all($prefix);
        $config = [];

        foreach ($result as $item) {
            $key = $item['Key'] ?? '';
            $decoded = base64_decode($item['Value'] ?? '', true);
            $value = $decoded !== false ? $decoded : ($item['Value'] ?? '');
            $config[$key] = $value;
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, $config, $this->cacheTtl);
        }

        return $config;
    }

    public function set(string $key, string $value): bool
    {
        return $this->kv->put($key, $value);
    }

    public function delete(string $key): bool
    {
        return $this->kv->delete($key);
    }

    public function watch(string $prefix): Watcher
    {
        return new Watcher(
            $this->kv,
            $prefix,
            $this->eventDispatcher
        );
    }
}
