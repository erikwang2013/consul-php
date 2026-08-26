<?php

namespace Erikwang2013\Consul\Tests\Service;

use Erikwang2013\Consul\Api\Health;
use Erikwang2013\Consul\Service\Discovery;
use Erikwang2013\Consul\Service\LoadBalancer\LoadBalancerInterface;
use Erikwang2013\Consul\Tests\Support\ArrayCache;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DiscoveryTest extends TestCase
{
    private $health;
    private Discovery $discovery;

    protected function setUp(): void
    {
        $this->health = $this->createMock(Health::class);
        $this->discovery = new Discovery($this->health);
    }

    public function testHealthyInstances(): void
    {
        $this->health->method('service')
            ->with('user-service', ['passing' => 'true'])
            ->willReturn([
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => ['v1'], 'Meta' => []],
                ],
            ]);

        $instances = $this->discovery->healthyInstances('user-service');

        $this->assertCount(1, $instances);
        $this->assertSame('10.0.0.1', $instances[0]['address']);
        $this->assertSame(8080, $instances[0]['port']);
        $this->assertSame('v1', $instances[0]['tags'][0]);
    }

    public function testHealthyInstancesUsesNodeAddressWhenServiceAddressIsEmpty(): void
    {
        $this->health->method('service')
            ->with('user-service', ['passing' => 'true'])
            ->willReturn([
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.1.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                ],
            ]);

        $instances = $this->discovery->healthyInstances('user-service');

        $this->assertSame('10.0.1.1', $instances[0]['address']);
    }

    public function testSelectInstance(): void
    {
        $this->health->method('service')
            ->with('user-service', ['passing' => 'true'])
            ->willReturn([
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                ],
            ]);

        $instance = $this->discovery->selectInstance('user-service');

        $this->assertNotNull($instance);
        $this->assertSame('10.0.0.1', $instance['address']);
    }

    public function testSelectInstanceReturnsNullWhenNoService(): void
    {
        $this->health->method('service')
            ->with('no-service', ['passing' => 'true'])
            ->willReturn([]);

        $instance = $this->discovery->selectInstance('no-service');

        $this->assertNull($instance);
    }

    public function testCacheKeyDistinguishesDatacenter(): void
    {
        $cache = new ArrayCache();
        $discovery = new Discovery($this->health, $cache, 60);
        $this->health->method('service')->willReturn([
            [
                'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
            ],
        ]);

        $discovery->healthyInstances('user-service', ['dc' => 'dc1']);
        $discovery->healthyInstances('user-service', ['dc' => 'dc2']);

        $this->assertSame(
            ['consul:discovery:user-service:' . md5(json_encode(['dc' => 'dc1'])), 'consul:discovery:user-service:' . md5(json_encode(['dc' => 'dc2']))],
            $cache->keys()
        );
        $this->assertFalse($cache->has('consul:discovery:user-service'));
    }

    public function testWithoutOptionsUsesPlainCacheKeyAndHits(): void
    {
        $cache = new ArrayCache();
        $discovery = new Discovery($this->health, $cache, 60);

        $calls = 0;
        $this->health->method('service')->willReturnCallback(function () use (&$calls) {
            $calls++;
            return [
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                ],
            ];
        });

        $discovery->healthyInstances('user-service');
        $discovery->healthyInstances('user-service');

        $this->assertSame(1, $calls);
        $this->assertSame(['consul:discovery:user-service'], $cache->keys());
    }

    public function testHealthyInstancesWithIndexOptionSkipsCache(): void
    {
        $cache = new ArrayCache();
        $discovery = new Discovery($this->health, $cache, 60);

        $calls = 0;
        $this->health->method('service')->willReturnCallback(function () use (&$calls) {
            $calls++;
            return [
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                ],
            ];
        });

        $discovery->healthyInstances('user-service', ['index' => '42']);
        $discovery->healthyInstances('user-service', ['index' => '43']);

        $this->assertSame(2, $calls);
        $this->assertSame([], $cache->keys()); // blocking queries are never cached
    }

    public function testSelectInstanceUsesProvidedLoadBalancer(): void
    {
        $lb = $this->createMock(LoadBalancerInterface::class);
        $discovery = new Discovery($this->health, null, null, $lb);

        $this->health->method('service')->willReturn([
            [
                'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
            ],
        ]);

        $lb->expects($this->once())
            ->method('select')
            ->with($this->callback(function ($instances) {
                return count($instances) === 1 && $instances[0]['address'] === '10.0.0.1';
            }))
            ->willReturn(['address' => '10.0.0.1', 'port' => 8080]);

        $instance = $discovery->selectInstance('user-service');

        $this->assertSame('10.0.0.1', $instance['address']);
    }

    public function testWatchLoopsWithIndexProgressionUntilStopped(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $discovery = new Discovery(new Health($transport));

        $queries = [];
        $transport->method('getWithHeaders')->willReturnCallback(
            function ($path, $query) use (&$queries, $discovery) {
                $queries[] = [$path, $query];
                if (count($queries) === 2) {
                    $discovery->stop();
                }
                return [
                    'headers' => ['X-Consul-Index' => (string) (41 + count($queries))],
                    'body'    => [
                        [
                            'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                            'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                        ],
                    ],
                ];
            }
        );

        $received = [];
        $discovery->watch('user-service', function ($instances) use (&$received) {
            $received[] = $instances;
        });

        $this->assertCount(2, $queries);
        $this->assertSame('/v1/health/service/user-service', $queries[0][0]);
        $this->assertSame(['passing' => 'true', 'index' => 0, 'wait' => '30s'], $queries[0][1]);
        $this->assertSame(42, $queries[1][1]['index']);
        $this->assertSame('10.0.0.1', $received[0][0]['address']);
    }

    public function testWatchErrorIsLoggedAndLoopContinues(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $discovery = new Discovery(new Health($transport), null, null, null, $logger);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $discovery) {
                $calls++;
                if ($calls === 1) {
                    throw new \RuntimeException('network down');
                }
                $discovery->stop();
                return ['headers' => ['X-Consul-Index' => '1'], 'body' => []];
            }
        );

        $logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Discovery watch error for user-service: network down'));

        $discovery->watch('user-service', function () {
        });
    }
}
