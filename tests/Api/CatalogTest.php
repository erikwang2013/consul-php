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

    public function testRegisterMapsAllFieldsToConsulNames(): void
    {
        $check = ['Node' => 'node1', 'CheckID' => 'node-ttl', 'TTL' => '30s'];
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) use ($check) {
                return $payload === [
                    'Node'             => 'node1',
                    'Address'          => '10.0.0.1',
                    'ID'               => 'node-uuid',
                    'TaggedAddresses'  => ['lan' => '10.0.0.1', 'wan' => '1.2.3.4'],
                    'Service'          => [
                        'Service'             => 'web',
                        'Address'             => '10.0.0.2',
                        'Port'                => 8080,
                        'ID'                  => 'web-1',
                        'Kind'                => 'connect-proxy',
                        'Tags'                => ['v1'],
                        'Meta'                => ['env' => 'prod'],
                        'Weights'             => ['Passing' => 10, 'Warning' => 1],
                        'EnableTagOverride'   => true,
                        'Proxy'               => ['DestinationServiceName' => 'api'],
                        'Check'               => ['TTL' => '10s'],
                    ],
                    'Check'            => $check,
                ];
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            [
                'node'             => 'node1',
                'address'          => '10.0.0.1',
                'id'               => 'node-uuid',
                'tagged_addresses' => ['lan' => '10.0.0.1', 'wan' => '1.2.3.4'],
            ],
            [
                'service'             => 'web',
                'address'             => '10.0.0.2',
                'port'                => 8080,
                'id'                  => 'web-1',
                'kind'                => 'connect-proxy',
                'tags'                => ['v1'],
                'meta'                => ['env' => 'prod'],
                'weights'             => ['Passing' => 10, 'Warning' => 1],
                'enable_tag_override' => true,
                'proxy'               => ['DestinationServiceName' => 'api'],
                'check'               => ['TTL' => '10s'],
            ],
            $check
        );

        $this->assertSame([], $result);
    }

    public function testRegisterConnectProxyService(): void
    {
        // mesh / gateway 注册靠 Kind + Proxy + Connect，旧的固定白名单表达不了这些字段
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return $payload['Service']['Kind'] === 'connect-proxy'
                    && $payload['Service']['Proxy'] === ['DestinationServiceName' => 'web']
                    && $payload['Service']['Connect'] === ['SidecarService' => ['Port' => 8080]];
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            [
                'service' => 'web-proxy',
                'port'    => 21000,
                'kind'    => 'connect-proxy',
                'proxy'   => ['DestinationServiceName' => 'web'],
                'connect' => ['SidecarService' => ['Port' => 8080]],
            ]
        );

        $this->assertSame([], $result);
    }

    public function testRegisterServiceCheckDoesNotBecomeNodeCheck(): void
    {
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return $payload['Service']['Check'] === ['TTL' => '10s']
                    && !isset($payload['Check']);
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web', 'check' => ['TTL' => '10s']]
        );

        $this->assertSame([], $result);
    }

    public function testRegisterWithoutPortOmitsPort(): void
    {
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return !array_key_exists('Port', $payload['Service']);
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web']
        );

        $this->assertSame([], $result);
    }

    public function testRegisterRejectsMissingRequiredFields(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->catalog->register(['node' => 'node1'], ['service' => 'web']);
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

    public function testDatacenters(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/datacenters')
            ->willReturn(['dc1', 'dc2']);

        $this->assertSame(['dc1', 'dc2'], $this->catalog->datacenters());
    }

    public function testDatacentersReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/datacenters')
            ->willReturn([]);

        $this->assertSame([], $this->catalog->datacenters());
    }

    public function testNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', ['dc' => 'dc1'])
            ->willReturn([['Node' => 'node1']]);

        $result = $this->catalog->nodes(['dc' => 'dc1']);

        $this->assertCount(1, $result);
    }

    public function testNodesForwardsStale(): void
    {
        // 归一成字符串 "true"（上游是 b.Get("stale") == "true"），布尔 true 会编成 stale=1 而读不到
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', ['stale' => 'true'])
            ->willReturn([['Node' => 'node1']]);

        $this->assertCount(1, $this->catalog->nodes(['stale' => true]));
    }

    public function testServicesForwardsConsistent(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/services', ['consistent' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->services(['consistent' => true]));
    }

    public function testServiceForwardsMaxStale(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/service/web', ['max_stale' => '10s'])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->service('web', ['max_stale' => '10s']));
    }

    public function testNodesDropFalseConsistencyFlags(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', [])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->nodes(['stale' => false, 'consistent' => false]));
    }

    public function testNodesWithNodeMetaSendsRepeatedKeys(): void
    {
        // node-meta 是重复键参数：模块以数组交给传输层展开（http_build_query 会编成 node-meta[0]=，服务端读不到）
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/catalog/nodes', ['node-meta' => ['rack=2', 'zone=a']])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->nodes(['node_meta' => ['rack=2', 'zone=a']]));
    }

    public function testNodeWithSingleNodeMeta(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/node/node1', ['node-meta' => ['rack=2']])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->node('node1', ['node_meta' => 'rack=2']));
    }

    public function testServiceWithStaleAndNodeMeta(): void
    {
        // 一致性参数归一成字符串 'true'，node-meta 以数组交给传输层展开成重复键
        $this->transport->method('get')
            ->with('/v1/catalog/service/web', ['dc' => 'dc1', 'stale' => 'true', 'node-meta' => ['rack=2', 'zone=a']])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->service('web', [
            'dc'        => 'dc1',
            'stale'     => true,
            'node_meta' => ['rack=2', 'zone=a'],
        ]));
    }

    public function testNodesWithEmptyNodeMetaFallsBackToArrayQuery(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', [])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->nodes(['node_meta' => []]));
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

    public function testGatewayServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/gateway-services/my-gw', [])
            ->willReturn([['Gateway' => 'my-gw', 'GatewayKind' => 'ingress', 'Service' => 'web']]);

        $result = $this->catalog->gatewayServices('my-gw');

        $this->assertSame('web', $result[0]['Service']);
    }

    public function testGatewayServicesForwardsOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/gateway-services/my-gw', ['dc' => 'dc2'])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->gatewayServices('my-gw', ['dc' => 'dc2']));
    }

    public function testGatewayServicesDropsUnknownOptions(): void
    {
        // 与 Catalog 其他读端点共用白名单：未识别参数不发出
        $this->transport->method('get')
            ->with('/v1/catalog/gateway-services/my-gw', ['dc' => 'dc1'])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->gatewayServices('my-gw', ['dc' => 'dc1', 'bogus' => 'x']));
    }

    public function testGatewayServicesUrlEncodesGatewayName(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/gateway-services/my%20gw%2F1', [])
            ->willReturn([]);

        $this->assertSame([], $this->catalog->gatewayServices('my gw/1'));
    }
}
