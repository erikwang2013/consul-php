<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Transport\TransportInterface;
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
            ->with('/v1/health/service/web', ['passing' => 'true'])
            ->willReturn([
                ['Node' => ['Node' => 'node1'], 'Service' => ['Service' => 'web'], 'Checks' => [['Status' => 'passing']]],
            ]);

        $result = $this->health->service('web', ['passing' => true]);

        $this->assertCount(1, $result);
        $this->assertSame('passing', $result[0]['Checks'][0]['Status']);
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/checks/web', [])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'passing']]);

        $result = $this->health->checks('web');

        $this->assertSame('passing', $result[0]['Status']);
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/node/node1', [])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->node('node1');

        $this->assertSame('node1', $result[0]['Node']['Node']);
    }

    public function testState(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/state/critical', ['dc' => 'dc1'])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'critical']]);

        $result = $this->health->state('critical', ['dc' => 'dc1']);

        $this->assertSame('critical', $result[0]['Status']);
    }
}
