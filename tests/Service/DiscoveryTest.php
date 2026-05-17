<?php

namespace Erikwang2013\Consul\Tests\Service;

use Erikwang2013\Consul\Api\Health;
use Erikwang2013\Consul\Service\Discovery;
use PHPUnit\Framework\TestCase;

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
}
