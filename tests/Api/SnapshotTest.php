<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Snapshot;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class SnapshotTest extends TestCase
{
    private $transport;
    private Snapshot $snapshot;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->snapshot = new Snapshot($this->transport);
    }

    public function testSave(): void
    {
        $this->transport->method('get')
            ->with('/v1/snapshot', [])
            ->willReturn(['body' => 'snapshot-data']);

        $result = $this->snapshot->save();

        $this->assertSame('snapshot-data', $result);
    }

    public function testSaveWithOptions(): void
    {
        $this->transport->method('get')
            ->with('/v1/snapshot', ['dc' => 'dc1', 'stale' => 'true'])
            ->willReturn(['body' => 'snapshot-data']);

        $result = $this->snapshot->save(['dc' => 'dc1', 'stale' => true]);

        $this->assertSame('snapshot-data', $result);
    }

    public function testSaveJsonFallback(): void
    {
        $this->transport->method('get')
            ->with('/v1/snapshot', [])
            ->willReturn(['key' => 'value']);

        $result = $this->snapshot->save();

        $this->assertSame('{"key":"value"}', $result);
    }

    public function testRestore(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/snapshot', ['body' => 'snapshot-data'], []);

        $this->snapshot->restore('snapshot-data');
    }

    public function testRestoreWithDc(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/snapshot', ['body' => 'snapshot-data'], ['dc' => 'dc1']);

        $this->snapshot->restore('snapshot-data', ['dc' => 'dc1']);
    }
}
