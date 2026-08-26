<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Health;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private $transport;
    private Health $health;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->health = new Health($this->transport);
    }

    public function testServiceWithPassingFilter(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/service/web', ['passing' => true])
            ->willReturn([
                ['Node' => ['Node' => 'node1'], 'Service' => ['Service' => 'web'], 'Checks' => [['Status' => 'passing']]],
            ]);

        $result = $this->health->service('web', ['passing' => true]);

        $this->assertCount(1, $result);
        $this->assertSame('passing', $result[0]['Checks'][0]['Status']);
    }

    public function testServiceWithAllOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/service/web', [
                'dc' => 'dc1',
                'ns' => 'prod',
                'filter' => 'Service.Meta.version==1',
                'index' => '10',
                'wait' => '5s',
                'passing' => true,
                'near' => 'node1',
            ])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->service('web', [
            'dc' => 'dc1',
            'ns' => 'prod',
            'filter' => 'Service.Meta.version==1',
            'index' => '10',
            'wait' => '5s',
            'passing' => true,
            'near' => 'node1',
        ]);

        $this->assertCount(1, $result);
    }

    public function testServiceReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/service/web', [])
            ->willReturn([]);

        $result = $this->health->service('web');

        $this->assertSame([], $result);
    }

    public function testServiceUrlEncodesName(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/service/web%20api', [])
            ->willReturn([]);

        $result = $this->health->service('web api');

        $this->assertSame([], $result);
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/checks/web', [])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'passing']]);

        $result = $this->health->checks('web');

        $this->assertSame('passing', $result[0]['Status']);
    }

    public function testChecksWithFilterOption(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/checks/web', ['filter' => 'Status==critical'])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'critical']]);

        $result = $this->health->checks('web', ['filter' => 'Status==critical']);

        $this->assertSame('critical', $result[0]['Status']);
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/node/node1', [])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->node('node1');

        $this->assertSame('node1', $result[0]['Node']['Node']);
    }

    public function testNodeWithNodeMetaOptionRenamesKey(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/node/node1', ['node-meta' => 'rack=2'])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->node('node1', ['node_meta' => 'rack=2']);

        $this->assertCount(1, $result);
    }

    public function testNodeUrlEncodesSpecialCharacters(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/node/web%20node%2F01', [])
            ->willReturn([]);

        $result = $this->health->node('web node/01');

        $this->assertSame([], $result);
    }

    public function testState(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/state/critical', ['dc' => 'dc1'])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'critical']]);

        $result = $this->health->state('critical', ['dc' => 'dc1']);

        $this->assertSame('critical', $result[0]['Status']);
    }

    public function testStateWithAllOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/state/critical', ['dc' => 'dc1', 'ns' => 'prod', 'filter' => 'Node.Node==n1'])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'critical']]);

        $result = $this->health->state('critical', ['dc' => 'dc1', 'ns' => 'prod', 'filter' => 'Node.Node==n1']);

        $this->assertCount(1, $result);
    }

    public function testStateIgnoresUnknownOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/state/passing', [])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'passing']]);

        $result = $this->health->state('passing', ['unknown' => 'x']);

        $this->assertSame('passing', $result[0]['Status']);
    }

    public function testConnect(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/connect/web', [])
            ->willReturn([['Node' => ['Node' => 'node1'], 'Service' => ['Service' => 'web']]]);

        $result = $this->health->connect('web');

        $this->assertCount(1, $result);
        $this->assertSame('web', $result[0]['Service']['Service']);
    }

    public function testConnectWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/connect/web', ['dc' => 'dc1', 'passing' => true])
            ->willReturn([]);

        $result = $this->health->connect('web', ['dc' => 'dc1', 'passing' => true]);

        $this->assertSame([], $result);
    }

    public function testIngress(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/ingress/web', [])
            ->willReturn([['Node' => ['Node' => 'node1'], 'Service' => ['Service' => 'web']]]);

        $result = $this->health->ingress('web');

        $this->assertCount(1, $result);
        $this->assertSame('node1', $result[0]['Node']['Node']);
    }

    public function testIngressReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/ingress/web', [])
            ->willReturn([]);

        $result = $this->health->ingress('web');

        $this->assertSame([], $result);
    }

    public function testGetTransportReturnsSameInstance(): void
    {
        $this->assertSame($this->transport, $this->health->getTransport());
    }
}
