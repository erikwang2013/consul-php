<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Service;

use Erikwang2013\Consul\Api\Health;
use Erikwang2013\Consul\Service\LoadBalancer\LoadBalancerInterface;
use Erikwang2013\Consul\Service\LoadBalancer\RoundRobin;
use Erikwang2013\Consul\Transport\TransportInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use Throwable;

class Discovery
{
    private Health $health;
    private TransportInterface $transport;
    private ?CacheInterface $cache;
    private ?int $cacheTtl;
    private LoadBalancerInterface $loadBalancer;
    private LoggerInterface $logger;
    private bool $running = false;

    public function __construct(
        Health $health,
        ?CacheInterface $cache = null,
        ?int $cacheTtl = null,
        ?LoadBalancerInterface $loadBalancer = null,
        ?LoggerInterface $logger = null
    ) {
        $this->health = $health;
        $this->transport = $health->getTransport();
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl ?? 300;
        $this->loadBalancer = $loadBalancer ?? new RoundRobin();
        $this->logger = $logger ?? new NullLogger();
    }

    public function healthyInstances(string $service, array $options = []): array
    {
        $cacheKey = "consul:discovery:{$service}";
        $opts = $options;
        unset($opts['index'], $opts['wait']);
        if (!empty($opts)) {
            $cacheKey .= ':' . md5(json_encode($opts));
        }

        if ($this->cache && !isset($options['index']) && !isset($options['wait'])) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $query = array_merge(['passing' => 'true'], $options);
        $result = $this->health->service($service, $query);

        $instances = $this->normalizeInstances($result);

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

        /** @phpstan-ignore-next-line */
        while ($this->running) {
            try {
                $response = $this->transport->getWithHeaders('/v1/health/service/' . rawurlencode($service), [
                    'passing' => 'true',
                    'index'   => $index,
                    'wait'    => $options['wait'] ?? '30s',
                ]);

                $index = (int) ($response['headers']['X-Consul-Index'] ?? $index);

                $instances = $this->normalizeInstances($response['body']);

                $callback($instances);
            } catch (Throwable $e) {
                $this->logger->warning("Discovery watch error for {$service}: " . $e->getMessage());
                sleep(1);
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function normalizeInstances(array $entries): array
    {
        return array_map(function ($entry) {
            return [
                'node'    => $entry['Node']['Node'] ?? '',
                'address' => $entry['Service']['Address'] ?: ($entry['Node']['Address'] ?? ''),
                'port'    => $entry['Service']['Port'] ?? 0,
                'service' => $entry['Service']['Service'] ?? '',
                'id'      => $entry['Service']['ID'] ?? '',
                'tags'    => $entry['Service']['Tags'] ?? [],
                'meta'    => $entry['Service']['Meta'] ?? [],
            ];
        }, $entries);
    }
}
