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

    public function testListWithBlockingQuery(): void
    {
        // 上游 EventList 走 parseBlockingQuery，events 支持阻塞查询
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/event/list', ['index' => '10', 'wait' => '5m'])
            ->willReturn([['ID' => 'evt-2', 'Name' => 'deploy']]);

        $result = $this->event->list(['index' => '10', 'wait' => '5m']);

        $this->assertSame('deploy', $result[0]['Name']);
    }

    public function testListWithDcAndFilter(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/event/list', ['dc' => 'dc2', 'filter' => 'Name==deploy'])
            ->willReturn([]);

        $this->assertSame([], $this->event->list(['dc' => 'dc2', 'filter' => 'Name==deploy']));
    }

    public function testListWithAllOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/event/list', [
                'name' => 'deploy',
                'dc' => 'dc2',
                'filter' => 'Name==deploy',
                'index' => '42',
                'wait' => '30s',
            ])
            ->willReturn([['ID' => 'evt-3']]);

        $result = $this->event->list([
            'name' => 'deploy',
            'dc' => 'dc2',
            'filter' => 'Name==deploy',
            'index' => '42',
            'wait' => '30s',
        ]);

        $this->assertCount(1, $result);
    }

    public function testListIgnoresUnknownOptions(): void
    {
        // ns / partition 是企业版概念，CE 下不应发出
        $this->transport->expects($this->once())
            ->method('get')
            ->with('/v1/event/list', [])
            ->willReturn([]);

        $this->assertSame([], $this->event->list(['unknown' => 'x', 'ns' => 'prod']));
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
