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

    public function testSaveWithDcOnly(): void
    {
        $this->transport->method('getRaw')
            ->with('/v1/snapshot', ['dc' => 'dc1'])
            ->willReturn('snapshot-binary-data');

        $result = $this->snapshot->save(['dc' => 'dc1']);

        $this->assertSame('snapshot-binary-data', $result);
    }

    public function testSaveWithStaleFalseOmitsStale(): void
    {
        $this->transport->method('getRaw')
            ->with('/v1/snapshot', ['dc' => 'dc1'])
            ->willReturn('snapshot-binary-data');

        $result = $this->snapshot->save(['dc' => 'dc1', 'stale' => false]);

        $this->assertSame('snapshot-binary-data', $result);
    }

    public function testSaveReturnsEmptyString(): void
    {
        $this->transport->method('getRaw')
            ->with('/v1/snapshot', [])
            ->willReturn('');

        $result = $this->snapshot->save();

        $this->assertSame('', $result);
    }

    public function testSaveWithBinaryData(): void
    {
        $binary = "SNAP\x00\x01\x02\xFF.gz";
        $this->transport->method('getRaw')
            ->with('/v1/snapshot', [])
            ->willReturn($binary);

        $result = $this->snapshot->save();

        $this->assertSame($binary, $result);
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

    public function testRestoreWithBinaryData(): void
    {
        $binary = "SNAP\x00\x01\x02\xFF.gz";
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/snapshot', $binary, []);

        $this->snapshot->restore($binary);
    }

    public function testRestoreEmptySnapshot(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/snapshot', '', []);

        $this->snapshot->restore('');
    }

    public function testRestoreIgnoresUnknownOptions(): void
    {
        $this->transport->expects($this->once())
            ->method('putRaw')
            ->with('/v1/snapshot', 'snapshot-data', []);

        $this->snapshot->restore('snapshot-data', ['stale' => true]);
    }
}
