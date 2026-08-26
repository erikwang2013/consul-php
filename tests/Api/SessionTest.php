<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Session;
use Erikwang2013\Consul\Exception\ClientException;
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

        $this->assertSame('abc-123', $this->session->create()['ID']);
    }

    public function testCreateWithOptions(): void
    {
        $opts = ['Name' => 'my-session', 'TTL' => '30s', 'Behavior' => 'delete'];
        $this->transport->method('put')
            ->with('/v1/session/create', $opts)
            ->willReturn(['ID' => 'abc-123']);

        $this->assertSame('abc-123', $this->session->create($opts)['ID']);
    }

    public function testCreatePropagatesTransportError(): void
    {
        $this->transport->method('put')->willThrowException(new ClientException('down'));

        $this->expectException(ClientException::class);
        $this->session->create();
    }

    public function testDestroy(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/session/destroy/abc-123', [], []);

        $this->session->destroy('abc-123');
    }

    public function testDestroyWithOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/session/destroy/abc-123', [], ['dc' => 'dc1']);

        $this->session->destroy('abc-123', ['dc' => 'dc1']);
    }

    public function testInfo(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/info/abc-123', [])
            ->willReturn([['ID' => 'abc-123', 'Name' => 'my-session']]);

        $this->assertSame('my-session', $this->session->info('abc-123')[0]['Name']);
    }

    public function testInfoWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/info/abc-123', ['dc' => 'dc1'])
            ->willReturn([['ID' => 'abc-123']]);

        $this->assertSame('abc-123', $this->session->info('abc-123', ['dc' => 'dc1'])[0]['ID']);
    }

    public function testInfoReturnsEmptyListForUnknownSession(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/info/nope', [])
            ->willReturn([]);

        $this->assertSame([], $this->session->info('nope'));
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/node/node-1', [])
            ->willReturn([['ID' => 'abc-123', 'Node' => 'node-1']]);

        $this->assertSame('abc-123', $this->session->node('node-1')[0]['ID']);
    }

    public function testNodeEncodesNodeName(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/node/a%20b', [])
            ->willReturn([]);

        $this->assertSame([], $this->session->node('a b'));
    }

    public function testAll(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/list', [])
            ->willReturn([['ID' => 'abc-123'], ['ID' => 'def-456']]);

        $this->assertCount(2, $this->session->all());
    }

    public function testAllWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/list', ['dc' => 'dc1'])
            ->willReturn([]);

        $this->assertSame([], $this->session->all(['dc' => 'dc1']));
    }

    public function testRenew(): void
    {
        $this->transport->method('put')
            ->with('/v1/session/renew/abc-123', [], [])
            ->willReturn([['ID' => 'abc-123']]);

        $this->assertSame('abc-123', $this->session->renew('abc-123')[0]['ID']);
    }

    public function testRenewWithOptions(): void
    {
        $this->transport->method('put')
            ->with('/v1/session/renew/abc-123', [], ['dc' => 'dc1'])
            ->willReturn([['ID' => 'abc-123']]);

        $this->assertSame('abc-123', $this->session->renew('abc-123', ['dc' => 'dc1'])[0]['ID']);
    }
}
