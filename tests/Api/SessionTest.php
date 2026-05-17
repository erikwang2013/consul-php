<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Session;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private $transport;
    private Session $session;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->session = new Session($this->transport);
    }

    public function testCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/session/create', [])
            ->willReturn(['ID' => 'abc-123']);

        $result = $this->session->create();

        $this->assertSame('abc-123', $result['ID']);
    }

    public function testCreateWithOptions(): void
    {
        $opts = ['Name' => 'my-session', 'TTL' => '30s', 'Behavior' => 'delete'];
        $this->transport->method('put')
            ->with('/v1/session/create', $opts)
            ->willReturn(['ID' => 'abc-123']);

        $result = $this->session->create($opts);

        $this->assertSame('abc-123', $result['ID']);
    }

    public function testDestroy(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/session/destroy/abc-123', [], []);

        $this->session->destroy('abc-123');
    }

    public function testInfo(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/info/abc-123', [])
            ->willReturn(['ID' => 'abc-123', 'Name' => 'my-session']);

        $result = $this->session->info('abc-123');

        $this->assertSame('my-session', $result['Name']);
    }

    public function testRenew(): void
    {
        $this->transport->method('put')
            ->with('/v1/session/renew/abc-123', [], [])
            ->willReturn([['ID' => 'abc-123']]);

        $result = $this->session->renew('abc-123');

        $this->assertSame('abc-123', $result[0]['ID']);
    }
}
