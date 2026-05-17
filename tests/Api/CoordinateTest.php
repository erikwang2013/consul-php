<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Coordinate;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class CoordinateTest extends TestCase
{
    private $transport;
    private Coordinate $coordinate;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->coordinate = new Coordinate($this->transport);
    }

    public function testDatacenters(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/datacenters')
            ->willReturn(['dc1' => [], 'dc2' => []]);

        $result = $this->coordinate->datacenters();

        $this->assertArrayHasKey('dc1', $result);
        $this->assertArrayHasKey('dc2', $result);
    }

    public function testNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/nodes', [])
            ->willReturn([['Node' => 'web01']]);

        $result = $this->coordinate->nodes();

        $this->assertCount(1, $result);
        $this->assertSame('web01', $result[0]['Node']);
    }

    public function testNodesWithDc(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/nodes', ['dc' => 'dc1'])
            ->willReturn([['Node' => 'web01']]);

        $result = $this->coordinate->nodes(['dc' => 'dc1']);

        $this->assertCount(1, $result);
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/node/web01', [])
            ->willReturn(['Node' => 'web01', 'Coord' => []]);

        $result = $this->coordinate->node('web01');

        $this->assertSame('web01', $result['Node']);
    }
}
