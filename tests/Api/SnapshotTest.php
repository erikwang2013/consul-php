<?php

namespace Erikwang2013\Consul\Tests\Api;

use Erikwang2013\Consul\Api\Snapshot;
use Erikwang2013\Consul\Transport\TransportInterface;
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
        $this->transport->method('getRaw')
            ->with('/v1/snapshot', [])
            ->willReturn('snapshot-binary-data');

        $result = $this->snapshot->save();

        $this->assertSame('snapshot-binary-data', $result);
    }

    public function testSaveWithOptions(): void
    {
        $this->transport->method('getRaw')
            ->with('/v1/snapshot', ['dc' => 'dc1', 'stale' => 'true'])
            ->willReturn('snapshot-binary-data');

        $result = $this->snapshot->save(['dc' => 'dc1', 'stale' => true]);

        $this->assertSame('snapshot-binary-data', $result);
    }

    public function testRestore(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/snapshot', 'snapshot-data', []);

        $this->snapshot->restore('snapshot-data');
    }

    public function testRestoreWithDc(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/snapshot', 'snapshot-data', ['dc' => 'dc1']);

        $this->snapshot->restore('snapshot-data', ['dc' => 'dc1']);
    }
}
