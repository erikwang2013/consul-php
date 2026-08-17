<?php

namespace Erikwang2013\Consul\Tests\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Config\Watcher;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class WatcherTest extends TestCase
{
    public function testOnChangeRegistersCallback(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->onChange(function ($snap) { });

        $this->assertInstanceOf(Watcher::class, $result);
    }

    public function testSetBlockingWaitIsFluent(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->setBlockingWait(60);

        $this->assertSame($watcher, $result);
    }

    public function testSetPollIntervalIsFluent(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->setPollInterval(15);

        $this->assertSame($watcher, $result);
    }

    public function testBlockingQueryNotifiesOnChangeAndReusesIndex(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = new Watcher(new Kv($transport), 'app/');

        $queries = [];
        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function ($path, $query) use (&$queries, &$calls, $watcher) {
                $queries[] = [$path, $query];
                $calls++;
                if ($calls === 2) {
                    $watcher->stop();
                }
                return [
                    'headers' => ['X-Consul-Index' => (string) (41 + $calls)],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode("v{$calls}")]],
                ];
            }
        );

        $changes = [];
        $watcher->onChange(function ($snap) use (&$changes) {
            $changes[] = $snap;
        });

        $watcher->start();

        $this->assertCount(2, $queries);
        $this->assertSame('/v1/kv/app/', $queries[0][0]);
        $this->assertSame(['recurse' => 'true', 'index' => 0, 'wait' => '30s'], $queries[0][1]);
        // X-Consul-Index from the first response (42) drives the second request
        $this->assertSame(42, $queries[1][1]['index']);
        $this->assertCount(2, $changes);
        $this->assertSame(['app/x' => 'v2'], $changes[1]);
    }

    public function testBlockingFailureFallsBackToPollingThenResumesBlocking(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = (new Watcher(new Kv($transport), 'app/'))->setPollInterval(0);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $watcher) {
                $calls++;
                if ($calls === 1) {
                    throw new \RuntimeException('blocking query failed');
                }
                $watcher->stop();
                return [
                    'headers' => ['X-Consul-Index' => '42'],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                ];
            }
        );
        $transport->expects($this->once())
            ->method('get')
            ->with('/v1/kv/app/', ['recurse' => 'true'])
            ->willReturn([]);

        $changes = [];
        $watcher->onChange(function ($snap) use (&$changes) {
            $changes[] = $snap;
        });

        $watcher->start();

        $this->assertCount(2, $changes);
        $this->assertSame([], $changes[0]);
        $this->assertSame(['app/x' => 'v'], $changes[1]);
    }
}
