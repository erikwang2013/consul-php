<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\DiscoveryChain;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class DiscoveryChainTest extends TestCase
{
    private $transport;
    private DiscoveryChain $chain;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->chain = new DiscoveryChain($this->transport);
    }

    public function testRead(): void
    {
        $this->transport->method('get')
            ->with('/v1/discovery-chain/web', [])
            ->willReturn(['Chain' => ['ServiceName' => 'web', 'Nodes' => []]]);

        $result = $this->chain->read('web');

        $this->assertSame('web', $result['Chain']['ServiceName']);
    }

    public function testReadReturnsEmptyChain(): void
    {
        $this->transport->method('get')
            ->with('/v1/discovery-chain/web', [])
            ->willReturn(['Chain' => null]);

        $this->assertNull($this->chain->read('web')['Chain']);
    }

    public function testReadWithDc(): void
    {
        $this->transport->method('get')
            ->with('/v1/discovery-chain/web', ['dc' => 'dc2'])
            ->willReturn(['Chain' => ['ServiceName' => 'web']]);

        $result = $this->chain->read('web', ['dc' => 'dc2']);

        $this->assertSame('web', $result['Chain']['ServiceName']);
    }

    public function testReadWithCompileDc(): void
    {
        // 上游读的是 compile-dc（EvaluateInDatacenter），不是 compile
        $this->transport->method('get')
            ->with('/v1/discovery-chain/web', ['compile-dc' => 'dc2'])
            ->willReturn(['Chain' => []]);

        $this->assertSame(['Chain' => []], $this->chain->read('web', ['compile-dc' => 'dc2']));
    }

    public function testReadWithBlockingQueryOptions(): void
    {
        $options = ['dc' => 'dc1', 'index' => '42', 'wait' => '5m', 'stale' => true];

        $this->transport->method('get')
            ->with('/v1/discovery-chain/web', $options)
            ->willReturn(['Chain' => []]);

        $this->assertSame(['Chain' => []], $this->chain->read('web', $options));
    }

    public function testReadUrlEncodesSpecialCharacters(): void
    {
        $this->transport->method('get')
            ->with('/v1/discovery-chain/web%20api%2Fv1', [])
            ->willReturn(['Chain' => []]);

        $this->assertSame(['Chain' => []], $this->chain->read('web api/v1'));
    }

    public function testReadPropagatesTransportError(): void
    {
        $this->transport->method('get')->willThrowException(new ClientException('not found'));

        $this->expectException(ClientException::class);
        $this->chain->read('nope');
    }
}
