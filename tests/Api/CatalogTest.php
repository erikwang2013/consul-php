<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Catalog;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class CatalogTest extends TestCase
{
    private $transport;
    private Catalog $catalog;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->catalog = new Catalog($this->transport);
    }

    public function testRegisterWithNodeAndService(): void
    {
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return $payload['Node'] === 'node1'
                    && $payload['Service']['Service'] === 'web'
                    && $payload['Service']['Port'] === 80;
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web', 'port' => 80]
        );

        $this->assertSame([], $result);
    }

    public function testDeregister(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/catalog/deregister', ['Node' => 'node1', 'ServiceID' => 'web-1']);

        $this->catalog->deregister(
            ['node' => 'node1'],
            'web-1'
        );
    }

    public function testServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/services', [])
            ->willReturn(['web' => [], 'api' => []]);

        $result = $this->catalog->services();

        $this->assertArrayHasKey('web', $result);
        $this->assertArrayHasKey('api', $result);
    }

    public function testServiceNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/service/web', [])
            ->willReturn([
                ['Node' => 'node1', 'ServiceAddress' => '10.0.0.1', 'ServicePort' => 80],
            ]);

        $result = $this->catalog->service('web');

        $this->assertCount(1, $result);
        $this->assertSame('node1', $result[0]['Node']);
    }

    public function testNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', ['dc' => 'dc1'])
            ->willReturn([['Node' => 'node1']]);

        $result = $this->catalog->nodes(['dc' => 'dc1']);

        $this->assertCount(1, $result);
    }
}
