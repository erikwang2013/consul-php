<?php

namespace Erikwang2013\Consul\Service;

use Erikwang2013\Consul\Api\Health;
use Erikwang2013\Consul\Service\LoadBalancer\LoadBalancerInterface;
use Erikwang2013\Consul\Service\LoadBalancer\RoundRobin;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class Discovery
{
    private Health $health;
    private ?CacheInterface $cache;
    private ?int $cacheTtl;
    private LoadBalancerInterface $loadBalancer;
    private bool $running = false;

    public function __construct(
        Health $health,
        ?CacheInterface $cache = null,
        ?int $cacheTtl = null,
        ?LoadBalancerInterface $loadBalancer = null
    ) {
        $this->health = $health;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->loadBalancer = $loadBalancer ?? new RoundRobin();
    }

    public function healthyInstances(string $service, array $options = []): array
    {
        $cacheKey = "consul:discovery:{$service}";

        if ($this->cache && !isset($options['index']) && !isset($options['wait'])) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $query = array_merge(['passing' => 'true'], $options);
        $result = $this->health->service($service, $query);

        $instances = array_map(function ($entry) {
            return [
                'node'    => $entry['Node']['Node'] ?? '',
                'address' => $entry['Service']['Address'] ?: ($entry['Node']['Address'] ?? ''),
                'port'    => $entry['Service']['Port'] ?? 0,
                'service' => $entry['Service']['Service'] ?? '',
                'id'      => $entry['Service']['ID'] ?? '',
                'tags'    => $entry['Service']['Tags'] ?? [],
                'meta'    => $entry['Service']['Meta'] ?? [],
            ];
        }, $result);

        if ($this->cache && !isset($options['index']) && !isset($options['wait'])) {
            $this->cache->set($cacheKey, $instances, $this->cacheTtl);
        }

        return $instances;
    }

    public function selectInstance(string $service, array $options = []): ?array
    {
        $instances = $this->healthyInstances($service, $options);
        return $this->loadBalancer->select($instances);
    }

    public function watch(string $service, callable $callback, array $options = []): void
    {
        $this->running = true;
        $index = $options['index'] ?? 0;

        /* @phpstan-ignore-next-line running modified by stop() from another coroutine */
        while ($this->running) {
            try {
                $result = $this->healthyInstances($service, [
                    'index' => $index,
                    'wait'  => $options['wait'] ?? '30s',
                ]);

                $callback($result);
            } catch (Throwable $e) {
                sleep(1);
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }
}
