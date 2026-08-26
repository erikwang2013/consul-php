<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Event;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private $transport;
    private Event $event;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->event = new Event($this->transport);
    }

    public function testFire(): void
    {
        $this->transport->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy', 'Payload' => base64_encode('hello')], [])
            ->willReturn(['ID' => 'evt-1']);

        $result = $this->event->fire('deploy', 'hello');

        $this->assertSame('evt-1', $result['ID']);
    }

    public function testFireWithoutPayloadOmitsPayloadKey(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy'], []);

        $this->event->fire('deploy');
    }

    public function testFirePayloadIsBase64Encoded(): void
    {
        $payload = "hello\nworld\x00binary";
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy', 'Payload' => base64_encode($payload)], []);

        $this->event->fire('deploy', $payload);
    }

    public function testFireWithOptions(): void
    {
        $this->transport->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy'], ['dc' => 'dc1', 'node' => 'web01'])
            ->willReturn(['ID' => 'evt-2']);

        $result = $this->event->fire('deploy', '', ['dc' => 'dc1', 'node' => 'web01']);

        $this->assertSame('evt-2', $result['ID']);
    }

    public function testFireWithAllOptions(): void
    {
        $this->transport->method('put')
            ->with(
                '/v1/event/fire/deploy',
                ['Name' => 'deploy'],
                ['dc' => 'dc1', 'node' => 'web01', 'service' => 'web', 'tag' => 'v1']
            )
            ->willReturn(['ID' => 'evt-3']);

        $result = $this->event->fire('deploy', '', [
            'dc' => 'dc1',
            'node' => 'web01',
            'service' => 'web',
            'tag' => 'v1',
        ]);

        $this->assertSame('evt-3', $result['ID']);
    }

    public function testFireUrlEncodesName(): void
    {
        $this->transport->method('put')
            ->with('/v1/event/fire/deploy%20v2', ['Name' => 'deploy v2'], [])
            ->willReturn(['ID' => 'evt-4']);

        $result = $this->event->fire('deploy v2');

        $this->assertSame('evt-4', $result['ID']);
    }

    public function testFireIgnoresUnknownOptions(): void
    {
        $this->transport->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy'], [])
            ->willReturn(['ID' => 'evt-5']);

        $result = $this->event->fire('deploy', '', ['unknown' => 'x']);

        $this->assertSame('evt-5', $result['ID']);
    }

    public function testList(): void
    {
        $this->transport->method('get')
            ->with('/v1/event/list', [])
            ->willReturn([['ID' => 'evt-1', 'Name' => 'deploy']]);

        $result = $this->event->list();

        $this->assertCount(1, $result);
        $this->assertSame('deploy', $result[0]['Name']);
    }

    public function testListWithNameFilter(): void
    {
        $this->transport->method('get')
            ->with('/v1/event/list', ['name' => 'deploy'])
            ->willReturn([['ID' => 'evt-1', 'Name' => 'deploy']]);

        $result = $this->event->list(['name' => 'deploy']);

        $this->assertCount(1, $result);
    }

    public function testListReturnsEmptyArray(): void
    {
        $this->transport->method('get')
            ->with('/v1/event/list', [])
            ->willReturn([]);

        $result = $this->event->list();

        $this->assertSame([], $result);
    }
}
