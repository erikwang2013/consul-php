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
        // 单值也走同一条路径：node-meta 是重复键参数，模块只负责把值组织成数组
        $this->transport->method('get')
            ->with('/v1/health/node/node1', ['node-meta' => ['rack=2']])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->node('node1', ['node_meta' => 'rack=2']);

        $this->assertCount(1, $result);
    }

    public function testNodeWithMultipleNodeMetaSendsRepeatedKeys(): void
    {
        // Consul 读的是重复的纯 node-meta 键；node-meta[0]= 这种带下标的写法服务端解析不到
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/node/node1', ['node-meta' => ['rack=2', 'zone=a']])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $this->health->node('node1', ['node_meta' => ['rack=2', 'zone=a']]);
    }

    public function testServiceMixesRepeatedNodeMetaWithOtherOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with(
                '/v1/health/service/web',
                ['dc' => 'dc1', 'passing' => true, 'index' => '10', 'wait' => '5s', 'node-meta' => ['rack=2', 'zone=a']]
            )
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->service('web', [
            'dc' => 'dc1',
            'passing' => true,
            'index' => '10',
            'wait' => '5s',
            'node_meta' => ['rack=2', 'zone=a'],
        ]);

        $this->assertCount(1, $result);
    }

    public function testConnectSupportsMultipleNodeMeta(): void
    {
        // connect() 与其余五个端点共用同一条组装路径，根因只修一处
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/connect/web', ['node-meta' => ['rack=1', 'rack=2']])
            ->willReturn([]);

        $this->assertSame([], $this->health->connect('web', ['node_meta' => ['rack=1', 'rack=2']]));
    }

    public function testEmptyNodeMetaArrayIsOmitted(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/node/node1', [])
            ->willReturn([]);

        $this->assertSame([], $this->health->node('node1', ['node_meta' => []]));
    }

    public function testNodeMetaIsHandedToTransportAsArray(): void
    {
        // 值的转义归传输层负责（rawurlencode），模块只把 node-meta 组织成数组以便展开成重复键。
        // 路径里的空格仍是 %20 —— 那是路径转义，与查询串编码是两回事。
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/checks/web%20api', ['node-meta' => ['env=prod 1']])
            ->willReturn([]);

        $this->assertSame([], $this->health->checks('web api', ['node_meta' => 'env=prod 1']));
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

    public function testServiceWithStaleEmitsStaleTrue(): void
    {
        // 归一成字符串 "true"（与 Snapshot::save 一致），而不是布尔 true 编出来的 stale=1
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/service/web', ['stale' => 'true'])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->service('web', ['stale' => true]);

        $this->assertCount(1, $result);
    }

    public function testConsistentModeIsEmitted(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/node/node1', ['consistent' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->health->node('node1', ['consistent' => true]));
    }

    public function testStaleFalseOmitsStale(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/service/web', [])
            ->willReturn([]);

        $this->assertSame([], $this->health->service('web', ['stale' => false]));
    }

    public function testMaxStaleIsForwardedVerbatim(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/state/critical', ['max_stale' => '10s'])
            ->willReturn([]);

        $this->assertSame([], $this->health->state('critical', ['max_stale' => '10s']));
    }

    public function testEmptyMaxStaleIsOmitted(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/state/critical', [])
            ->willReturn([]);

        $this->assertSame([], $this->health->state('critical', ['max_stale' => '']));
    }

    public function testConsistencyCoexistsWithOtherOptions(): void
    {
        // 没有 node_meta 时仍走常规 query 数组（URL 由传输层拼），一致性参数与其它选项并存
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/service/web', [
                'dc' => 'dc1',
                'passing' => true,
                'index' => '10',
                'wait' => '5s',
                'stale' => 'true',
                'max_stale' => '5s',
            ])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->service('web', [
            'dc' => 'dc1',
            'passing' => true,
            'stale' => 'true',
            'max_stale' => '5s',
            'index' => '10',
            'wait' => '5s',
        ]);

        $this->assertCount(1, $result);
    }

    public function testConsistencyCoexistsWithRepeatedNodeMeta(): void
    {
        // 一致性参数与 node-meta 重复键共存：都由传输层统一编码
        $this->transport->expects($this->once())
            ->method('get')
            ->with(
                '/v1/health/node/node1',
                ['stale' => 'true', 'max_stale' => '5s', 'node-meta' => ['rack=2', 'zone=a']]
            )
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->node('node1', [
            'stale' => true,
            'max_stale' => '5s',
            'node_meta' => ['rack=2', 'zone=a'],
        ]);

        $this->assertCount(1, $result);
    }

    public function testIngressAlsoSupportsConsistency(): void
    {
        // 一致性参数加在六个端点共用的组装路径上，不是逐个调用方打补丁
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/ingress/web', ['stale' => 'true'])
            ->willReturn([]);

        $this->assertSame([], $this->health->ingress('web', ['stale' => true]));
    }

    public function testGetTransportReturnsSameInstance(): void
    {
        $this->assertSame($this->transport, $this->health->getTransport());
    }
    public function testStringFalseIsNotTreatedAsTrue(): void
    {
        // 从环境变量/配置拿到的 switch 常是字符串：'false' 在 !empty() 下为真，
        // 会让"要一致性读"变成"陈旧读"且不报错
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/health/node/node1', [])
            ->willReturn([]);

        $this->assertSame([], $this->health->node('node1', ['stale' => 'false', 'consistent' => 'off']));
    }
}
