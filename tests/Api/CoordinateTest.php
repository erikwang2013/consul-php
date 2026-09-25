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

    public function testDatacentersReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/datacenters')
            ->willReturn([]);

        $result = $this->coordinate->datacenters();

        $this->assertSame([], $result);
    }

    public function testDatacentersReturnsCoordFields(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/datacenters')
            ->willReturn(['dc1' => [['Node' => 'n1', 'Coord' => ['Vec' => [1.0]]]]]);

        $result = $this->coordinate->datacenters();

        $this->assertSame('n1', $result['dc1'][0]['Node']);
        $this->assertSame([1.0], $result['dc1'][0]['Coord']['Vec']);
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

    public function testNodesWithMultipleOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/nodes', ['dc' => 'dc1', 'ns' => 'prod', 'index' => '42', 'wait' => '5s'])
            ->willReturn([['Node' => 'web01']]);

        $result = $this->coordinate->nodes(['dc' => 'dc1', 'ns' => 'prod', 'index' => '42', 'wait' => '5s']);

        $this->assertCount(1, $result);
    }

    public function testNodesReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/nodes', [])
            ->willReturn([]);

        $result = $this->coordinate->nodes();

        $this->assertSame([], $result);
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/node/web01', [])
            ->willReturn(['Node' => 'web01', 'Coord' => []]);

        $result = $this->coordinate->node('web01');

        $this->assertSame('web01', $result['Node']);
    }

    public function testNodeWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/node/web01', ['dc' => 'dc1'])
            ->willReturn(['Node' => 'web01', 'Coord' => []]);

        $result = $this->coordinate->node('web01', ['dc' => 'dc1']);

        $this->assertSame('web01', $result['Node']);
    }

    public function testNodeUrlEncodesSpecialCharacters(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/node/web%20node%2F01', [])
            ->willReturn(['Node' => 'web node/01', 'Coord' => []]);

        $result = $this->coordinate->node('web node/01');

        $this->assertSame('web node/01', $result['Node']);
    }

    public function testNodeReturnsCoordStructure(): void
    {
        $coord = ['Vec' => [0.1, 0.2], 'Error' => 0.5, 'Height' => 1.0];
        $this->transport->method('get')
            ->with('/v1/coordinate/node/web01', [])
            ->willReturn(['Node' => 'web01', 'Coord' => $coord]);

        $result = $this->coordinate->node('web01');

        $this->assertSame($coord, $result['Coord']);
        $this->assertSame(0.5, $result['Coord']['Error']);
    }

    public function testUpdate(): void
    {
        $body = [
            'Node'    => 'web01',
            'Segment' => '',
            'Coord'   => ['Vec' => [0.1, 0.2], 'Error' => 0.5, 'Height' => 1.0],
        ];

        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/coordinate/update', $body)
            ->willReturn([]);

        $this->assertSame([], $this->coordinate->update($body));
    }

    public function testUpdateWithSegment(): void
    {
        $body = [
            'Node'    => 'web01',
            'Segment' => 'segment-a',
            'Coord'   => ['Vec' => [0.3], 'Error' => 0.1, 'Height' => 0.9],
        ];

        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/coordinate/update', $body)
            ->willReturn([]);

        $this->assertSame([], $this->coordinate->update($body));
    }

    public function testUpdateRequiresNode(): void
    {
        $this->transport->expects($this->never())->method('put');

        $this->expectException(\InvalidArgumentException::class);
        $this->coordinate->update(['Coord' => ['Vec' => [0.1]]]);
    }

    public function testUpdateRejectsEmptyNode(): void
    {
        $this->transport->expects($this->never())->method('put');

        $this->expectException(\InvalidArgumentException::class);
        $this->coordinate->update(['Node' => '', 'Coord' => ['Vec' => [0.1]]]);
    }

    public function testUpdateRejectsCoordinatesFieldName(): void
    {
        // 上游字段是 Coord。写成 Coordinates 会被服务端静默忽略（Coord 变 null），
        // 与其发出去让 RPC 拒绝，不如本地直接报错。
        $this->transport->expects($this->never())->method('put');

        $this->expectException(\InvalidArgumentException::class);
        $this->coordinate->update(['Node' => 'web01', 'Coordinates' => ['Vec' => [0.1]]]);
    }
}
