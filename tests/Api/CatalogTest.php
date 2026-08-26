<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Catalog;
use Erikwang2013\Consul\Transport\TransportInterface;
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
                    && $payload['Address'] === '10.0.0.1'
                    && $payload['Service']['Service'] === 'web'
                    && $payload['Service']['Address'] === '10.0.0.1'
                    && $payload['Service']['Port'] === 80;
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web', 'port' => 80]
        );

        $this->assertSame([], $result);
    }

    public function testRegisterWithFullOptions(): void
    {
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return $payload['Node'] === 'node1'
                    && $payload['Address'] === '10.0.0.1'
                    && $payload['Datacenter'] === 'dc1'
                    && $payload['NodeMeta'] === ['rack' => '2']
                    && $payload['Service'] === [
                        'Service' => 'web',
                        'Address' => '10.0.0.2',
                        'Port'    => 8080,
                        'ID'      => 'web-1',
                        'Tags'    => ['v1'],
                        'Meta'    => ['env' => 'prod'],
                    ];
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1', 'datacenter' => 'dc1', 'meta' => ['rack' => '2']],
            ['service' => 'web', 'address' => '10.0.0.2', 'port' => 8080, 'id' => 'web-1', 'tags' => ['v1'], 'meta' => ['env' => 'prod']]
        );

        $this->assertSame([], $result);
    }

    public function testRegisterServiceAddressDefaultsToNodeAddress(): void
    {
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return $payload['Service']['Address'] === '10.0.0.1'
                    && !isset($payload['Service']['ID'])
                    && !isset($payload['Service']['Tags'])
                    && !isset($payload['Datacenter'])
                    && !isset($payload['NodeMeta'])
                    && !isset($payload['Check']);
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web', 'port' => 80]
        );

        $this->assertSame([], $result);
    }

    public function testRegisterWithCheck(): void
    {
        $check = ['Node' => 'node1', 'CheckID' => 'svc:web', 'Status' => 'passing'];
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) use ($check) {
                return $payload['Check'] === $check;
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web', 'port' => 80],
            $check
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

    public function testDeregisterWithoutServiceId(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/catalog/deregister', ['Node' => 'node1']);

        $this->catalog->deregister(['node' => 'node1']);
    }

    public function testDeregisterWithDatacenter(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/catalog/deregister', ['Node' => 'node1', 'Datacenter' => 'dc1']);

        $this->catalog->deregister(['node' => 'node1', 'datacenter' => 'dc1']);
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

    public function testServicesWithDc(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/services', ['dc' => 'dc1'])
            ->willReturn(['web' => []]);

        $result = $this->catalog->services(['dc' => 'dc1']);

        $this->assertSame(['web' => []], $result);
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

    public function testServiceWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/service/web', ['dc' => 'dc1', 'filter' => 'Service.Meta.env==prod'])
            ->willReturn([]);

        $result = $this->catalog->service('web', ['dc' => 'dc1', 'filter' => 'Service.Meta.env==prod']);

        $this->assertSame([], $result);
    }

    public function testServiceUrlEncodesSpecialCharacters(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/service/web%20api%2Fv2', [])
            ->willReturn([]);

        $result = $this->catalog->service('web api/v2');

        $this->assertSame([], $result);
    }

    public function testNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', ['dc' => 'dc1'])
            ->willReturn([['Node' => 'node1']]);

        $result = $this->catalog->nodes(['dc' => 'dc1']);

        $this->assertCount(1, $result);
    }

    public function testNodesReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', [])
            ->willReturn([]);

        $result = $this->catalog->nodes();

        $this->assertSame([], $result);
    }

    public function testConnect(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/connect/web', [])
            ->willReturn([['Node' => 'node1', 'ServiceName' => 'web']]);

        $result = $this->catalog->connect('web');

        $this->assertCount(1, $result);
        $this->assertSame('web', $result[0]['ServiceName']);
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/node/node1', [])
            ->willReturn(['Node' => ['Node' => 'node1'], 'Services' => ['web' => []]]);

        $result = $this->catalog->node('node1');

        $this->assertSame('node1', $result['Node']['Node']);
        $this->assertArrayHasKey('web', $result['Services']);
    }

    public function testNodeServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/node-services/node1', [])
            ->willReturn(['Node' => ['Node' => 'node1'], 'Services' => ['web' => ['Service' => 'web']]]);

        $result = $this->catalog->nodeServices('node1');

        $this->assertSame('node1', $result['Node']['Node']);
    }

    public function testNodeServicesUrlEncodesSpecialCharacters(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/node-services/web%20node%2F1', [])
            ->willReturn([]);

        $result = $this->catalog->nodeServices('web node/1');

        $this->assertSame([], $result);
    }
}
