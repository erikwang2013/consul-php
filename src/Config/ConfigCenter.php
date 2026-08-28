<?php

declare(strict_types=1);

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
        $this->cacheTtl = $cacheTtl ?? 300;
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
        if ($decoded === false) {
            return $default;
        }
        $value = $decoded;

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
            $config[$item['Key'] ?? ''] = $this->kv->decodeValue($item);
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, $config, $this->cacheTtl);
        }

        return $config;
    }

    public function set(string $key, string $value): bool
    {
        $ok = $this->kv->put($key, $value);
        if ($ok) {
            $this->invalidate($key);
        }
        return $ok;
    }

    public function delete(string $key): bool
    {
        $ok = $this->kv->delete($key);
        if ($ok) {
            $this->invalidate($key);
        }
        return $ok;
    }

    private function invalidate(string $key): void
    {
        if (!$this->cache) {
            return;
        }

        $this->cache->delete("consul:config:{$key}");

        $prefix = '';
        foreach (explode('/', $key) as $part) {
            $prefix = $prefix === '' ? $part : $prefix . '/' . $part;
            $this->cache->delete("consul:config:ns:{$prefix}");
        }
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
