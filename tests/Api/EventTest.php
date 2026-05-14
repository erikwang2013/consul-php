<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Event;
use Erikwang\Consul\Transport\TransportInterface;
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

    public function testFireWithOptions(): void
    {
        $this->transport->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy'], ['dc' => 'dc1', 'node' => 'web01'])
            ->willReturn(['ID' => 'evt-2']);

        $result = $this->event->fire('deploy', '', ['dc' => 'dc1', 'node' => 'web01']);

        $this->assertSame('evt-2', $result['ID']);
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
}
