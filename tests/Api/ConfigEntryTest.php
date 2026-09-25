<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\ConfigEntry;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class ConfigEntryTest extends TestCase
{
    private $transport;
    private ConfigEntry $config;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->config = new ConfigEntry($this->transport);
    }

    public function testSet(): void
    {
        $entry = ['Kind' => 'service-defaults', 'Name' => 'web', 'Protocol' => 'http'];
        $this->transport->method('put')
            ->with('/v1/config', $entry, [])
            ->willReturn($entry);

        $this->assertSame('http', $this->config->set($entry)['Protocol']);
    }

    public function testSetWithOptions(): void
    {
        $entry = ['Kind' => 'mesh', 'Name' => 'mesh', 'TransparentProxy' => ['MeshDestinationsOnly' => true]];
        $this->transport->method('put')
            ->with('/v1/config', $entry, ['dc' => 'dc1', 'cas' => 12])
            ->willReturn($entry);

        $this->assertSame('mesh', $this->config->set($entry, ['dc' => 'dc1', 'cas' => 12])['Name']);
    }

    public function testSetPropagatesTransportError(): void
    {
        $this->transport->method('put')->willThrowException(new ClientException('bad kind'));

        $this->expectException(ClientException::class);
        $this->config->set(['Kind' => 'nope', 'Name' => 'x']);
    }

    public function testGet(): void
    {
        $this->transport->method('get')
            ->with('/v1/config/service-defaults/web', [])
            ->willReturn(['Kind' => 'service-defaults', 'Name' => 'web']);

        $this->assertSame('web', $this->config->get('service-defaults', 'web')['Name']);
    }

    public function testGetEncodesKindAndName(): void
    {
        $this->transport->method('get')
            ->with('/v1/config/service-defaults/a%20b', [])
            ->willReturn([]);

        $this->assertSame([], $this->config->get('service-defaults', 'a b'));
    }

    public function testGetWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/config/mesh/mesh', ['ns' => 'default'])
            ->willReturn(['Kind' => 'mesh', 'Name' => 'mesh']);

        $this->assertSame('mesh', $this->config->get('mesh', 'mesh', ['ns' => 'default'])['Name']);
    }

    public function testListAll(): void
    {
        $this->transport->method('get')
            ->with('/v1/config', [])
            ->willReturn([['Kind' => 'mesh', 'Name' => 'mesh'], ['Kind' => 'service-defaults', 'Name' => 'web']]);

        $this->assertCount(2, $this->config->list());
    }

    public function testListByKind(): void
    {
        $this->transport->method('get')
            ->with('/v1/config/ingress-gateway', [])
            ->willReturn([['Kind' => 'ingress-gateway', 'Name' => 'gw']]);

        $this->assertSame('gw', $this->config->list('ingress-gateway')[0]['Name']);
    }

    public function testListByKindWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/config/service-intentions', ['dc' => 'dc1'])
            ->willReturn([]);

        $this->assertSame([], $this->config->list('service-intentions', ['dc' => 'dc1']));
    }

    public function testDelete(): void
    {
        // 无 cas 时 Consul 返回空对象，PHP 侧解码成 []
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/config/service-defaults/web', [])
            ->willReturn([]);

        $this->assertSame([], $this->config->delete('service-defaults', 'web'));
    }

    public function testDeleteWithCasReturnsDeletedFlag(): void
    {
        // 带 cas 时 Consul 改返回 bool
        $this->transport->method('delete')
            ->with('/v1/config/service-defaults/web', ['cas' => 12])
            ->willReturn(['body' => true]);

        $this->assertSame(['body' => true], $this->config->delete('service-defaults', 'web', ['cas' => 12]));
    }

    public function testDeleteEncodesKindAndName(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/config/service-defaults/a%2Fb', [])
            ->willReturn([]);

        $this->config->delete('service-defaults', 'a/b');
    }
}
