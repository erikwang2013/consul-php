<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Connect;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Transport\Psr18Transport;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class ConnectTest extends TestCase
{
    private $transport;
    private Connect $connect;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->connect = new Connect($this->transport);
    }

    public function testIntentions(): void
    {
        $this->transport->method('get')
            ->with('/v1/connect/intentions', [])
            ->willReturn([['SourceName' => 'web', 'DestinationName' => 'db', 'Action' => 'allow']]);

        $this->assertSame('allow', $this->connect->intentions()[0]['Action']);
    }

    public function testIntentionsWithOptionsFiltersUnknownKeys(): void
    {
        $this->transport->method('get')
            ->with('/v1/connect/intentions', ['dc' => 'dc1', 'ns' => 'default'])
            ->willReturn([]);

        $this->assertSame([], $this->connect->intentions(['dc' => 'dc1', 'ns' => 'default', 'index' => 3]));
    }

    public function testIntentionCreate(): void
    {
        $ixn = ['SourceName' => 'web', 'DestinationName' => 'db', 'Action' => 'allow'];
        $this->transport->method('post')
            ->with('/v1/connect/intentions', $ixn, [])
            ->willReturn(['ID' => 'ixn-uuid']);

        $this->assertSame('ixn-uuid', $this->connect->intentionCreate($ixn)['ID']);
    }

    public function testIntentionReadUsesExactPathWithSourceAndDestinationQuery(): void
    {
        $this->transport->method('get')
            ->with('/v1/connect/intentions/exact', ['source' => 'web', 'destination' => 'db'])
            ->willReturn(['SourceName' => 'web', 'DestinationName' => 'db', 'Action' => 'deny']);

        $this->assertSame('deny', $this->connect->intentionRead('web', 'db')['Action']);
    }

    public function testIntentionReadPropagatesNotFound(): void
    {
        $this->transport->method('get')->willThrowException(new NotFoundException('no such intention'));

        $this->expectException(NotFoundException::class);
        $this->connect->intentionRead('web', 'db');
    }

    public function testIntentionReadWithOptions(): void
    {
        $this->transport->method('get')
            ->with(
                '/v1/connect/intentions/exact',
                ['source' => 'web', 'destination' => 'db', 'ns' => 'default']
            )
            ->willReturn([]);

        $this->assertSame([], $this->connect->intentionRead('web', 'db', ['ns' => 'default']));
    }

    public function testIntentionUpdate(): void
    {
        $ixn = ['Action' => 'allow'];
        $this->transport->method('put')
            ->with('/v1/connect/intentions/exact', $ixn, ['source' => 'web', 'destination' => 'db'])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->connect->intentionUpdate('web', 'db', $ixn));
    }

    public function testIntentionUpdateReturnsFalseWhenNotAcknowledged(): void
    {
        // 服务端未返回 JSON true（例如空 body）时不能假装成功
        $this->transport->method('put')->willReturn([]);

        $this->assertFalse($this->connect->intentionUpdate('web', 'db', ['Action' => 'deny']));
    }

    public function testIntentionDelete(): void
    {
        $this->transport->method('delete')
            ->with('/v1/connect/intentions/exact', ['source' => 'web', 'destination' => 'db'])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->connect->intentionDelete('web', 'db'));
    }

    public function testIntentionDeleteWildcardDestination(): void
    {
        // 目标是通配时删的是通配规则本身，* 必须原样出现在 name 位置
        $this->transport->method('delete')
            ->with('/v1/connect/intentions/exact', ['source' => 'web', 'destination' => '*'])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->connect->intentionDelete('web', '*'));
    }

    public function testIntentionReadById(): void
    {
        $this->transport->method('get')
            ->with('/v1/connect/intentions/ixn-uuid', [])
            ->willReturn(['ID' => 'ixn-uuid', 'Action' => 'allow']);

        $this->assertSame('ixn-uuid', $this->connect->intentionReadById('ixn-uuid')['ID']);
    }

    public function testIntentionUpdateById(): void
    {
        $this->transport->method('put')
            ->with('/v1/connect/intentions/ixn-uuid', ['Action' => 'deny'], [])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->connect->intentionUpdateById('ixn-uuid', ['Action' => 'deny']));
    }

    public function testIntentionDeleteById(): void
    {
        $this->transport->method('delete')
            ->with('/v1/connect/intentions/ixn-uuid', [])
            ->willReturn(['body' => true]);

        $this->assertTrue($this->connect->intentionDeleteById('ixn-uuid'));
    }

    public function testIntentionMatchSendsNamesAsArrayForRepeatedKeys(): void
    {
        // name 必须编成重复键，由传输层负责展开，故这里断言 query 的数组形态
        $this->transport->method('get')
            ->with('/v1/connect/intentions/match', ['by' => 'source', 'name' => ['web', 'api']])
            ->willReturn([[], []]);

        $this->assertCount(2, $this->connect->intentionMatch('source', ['web', 'api']));
    }

    public function testIntentionMatchSingleName(): void
    {
        $this->transport->method('get')
            ->with('/v1/connect/intentions/match', ['by' => 'destination', 'name' => ['db']])
            ->willReturn([[]]);

        $this->assertCount(1, $this->connect->intentionMatch('destination', ['db']));
    }

    public function testIntentionMatchKeepsExtraOptionsAndReindexesNames(): void
    {
        // 关联数组的 name 也要拍平成 list
        $this->transport->method('get')
            ->with(
                '/v1/connect/intentions/match',
                ['by' => 'source', 'name' => ['a b'], 'ns' => 'default']
            )
            ->willReturn([[]]);

        $this->assertCount(1, $this->connect->intentionMatch('source', ['x' => 'a b'], ['ns' => 'default']));
    }

    public function testIntentionMatchProducesRepeatedNameKeysOnTheWire(): void
    {
        // 本模块依赖传输层把数组值展开成重复键。Go 的 url.Values 只认重复纯键，
        // 一旦编成 name[0]=，Consul 会认为缺少必填的 name 参数并回 400，所以这里断言最终 URI。
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);
        $transport = new Psr18Transport($httpClient, $requestFactory, $streamFactory, 'http://127.0.0.1:8500');

        $request = $this->createMock(RequestInterface::class);
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('[]');
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $requestFactory->expects($this->once())
            ->method('createRequest')
            ->with(
                'GET',
                'http://127.0.0.1:8500/v1/connect/intentions/match?by=source&name=web&name=api'
            )
            ->willReturn($request);
        $httpClient->method('sendRequest')->willReturn($response);

        $connect = new Connect($transport);

        $this->assertSame([], $connect->intentionMatch('source', ['web', 'api']));
    }

    public function testIntentionCheck(): void
    {
        $this->transport->method('get')
            ->with('/v1/connect/intentions/check', ['source' => 'web', 'destination' => 'db'])
            ->willReturn(['Allowed' => true, 'Reason' => 'Allowed by intention']);

        $result = $this->connect->intentionCheck('web', 'db');

        $this->assertTrue($result['Allowed']);
        $this->assertSame('Allowed by intention', $result['Reason']);
    }

    public function testIntentionCheckWithSourceType(): void
    {
        $this->transport->method('get')
            ->with(
                '/v1/connect/intentions/check',
                ['source' => 'web', 'destination' => 'db', 'source-type' => 'consul']
            )
            ->willReturn(['Allowed' => false, 'Reason' => 'Denied']);

        $this->assertFalse(
            $this->connect->intentionCheck('web', 'db', ['source_type' => 'consul'])['Allowed']
        );
    }
}
