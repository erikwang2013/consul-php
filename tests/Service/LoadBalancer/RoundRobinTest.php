<?php

namespace Erikwang2013\Consul\Tests\Service\LoadBalancer;

use Erikwang2013\Consul\Service\LoadBalancer\RoundRobin;
use PHPUnit\Framework\TestCase;

class RoundRobinTest extends TestCase
{
    public function testSelectRotatesInstances(): void
    {
        $rr = new RoundRobin();
        $instances = [
            ['address' => '10.0.0.1', 'port' => 80],
            ['address' => '10.0.0.2', 'port' => 80],
            ['address' => '10.0.0.3', 'port' => 80],
        ];

        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.2', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.3', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
    }

    public function testSelectReturnsNullWhenEmpty(): void
    {
        $rr = new RoundRobin();
        $this->assertNull($rr->select([]));
    }

    public function testSelectNormalizesAssociativeKeys(): void
    {
        $rr = new RoundRobin();
        $instances = [
            'a' => ['address' => '10.0.0.1', 'port' => 80],
            'b' => ['address' => '10.0.0.2', 'port' => 80],
        ];

        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.2', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
    }

    public function testSelectSingleInstanceAlwaysReturnsIt(): void
    {
        $rr = new RoundRobin();
        $instances = [['address' => '10.0.0.9', 'port' => 80]];

        $this->assertSame('10.0.0.9', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.9', $rr->select($instances)['address']);
    }

    public function testEmptySelectionDoesNotAdvanceCounter(): void
    {
        $rr = new RoundRobin();
        $this->assertNull($rr->select([]));
        $this->assertNull($rr->select([]));

        $instances = [
            ['address' => '10.0.0.1', 'port' => 80],
            ['address' => '10.0.0.2', 'port' => 80],
        ];

        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
    }
}
