<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Status;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private $transport;
    private Status $status;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->status = new Status($this->transport);
    }

    public function testLeader(): void
    {
        $this->transport->method('get')
            ->with('/v1/status/leader')
            ->willReturn(['body' => '10.0.0.1:8300']);

        $result = $this->status->leader();

        $this->assertSame('10.0.0.1:8300', $result);
    }

    public function testLeaderEmptyBody(): void
    {
        $this->transport->method('get')
            ->with('/v1/status/leader')
            ->willReturn([]);

        $result = $this->status->leader();

        $this->assertSame('', $result);
    }

    public function testPeers(): void
    {
        $this->transport->method('get')
            ->with('/v1/status/peers')
            ->willReturn(['body' => ['10.0.0.1:8300', '10.0.0.2:8300']]);

        $result = $this->status->peers();

        $this->assertCount(2, $result);
        $this->assertSame('10.0.0.1:8300', $result[0]);
    }

    public function testPeersFallback(): void
    {
        $this->transport->method('get')
            ->with('/v1/status/peers')
            ->willReturn(['10.0.0.1:8300']);

        $result = $this->status->peers();

        $this->assertSame('10.0.0.1:8300', $result[0]);
    }
}
