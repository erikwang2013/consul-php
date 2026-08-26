<?php

namespace Erikwang2013\Consul\Tests\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Config\ConfigChangedEvent;
use Erikwang2013\Consul\Config\Watcher;
use Erikwang2013\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

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

    public function testSetBlockingWaitControlsWaitParameter(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = (new Watcher(new Kv($transport), 'app/'))->setBlockingWait(60);

        $waits = [];
        $transport->method('getWithHeaders')->willReturnCallback(
            function ($path, $query) use (&$waits, $watcher) {
                $waits[] = $query['wait'] ?? null;
                if (count($waits) === 2) {
                    $watcher->stop();
                }
                return [
                    'headers' => ['X-Consul-Index' => '1'],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                ];
            }
        );

        $watcher->start();

        $this->assertSame(['60s', '60s'], $waits);
    }

    public function testPollingFailureIsLoggedAndLoopContinues(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $watcher = (new Watcher(new Kv($transport), 'app/', null, $logger))->setPollInterval(0);

        $blockingCalls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$blockingCalls, $watcher) {
                $blockingCalls++;
                if ($blockingCalls === 1) {
                    throw new \RuntimeException('blocking down');
                }
                $watcher->stop();
                return ['headers' => [], 'body' => []];
            }
        );

        $pollCalls = 0;
        $transport->method('get')->willReturnCallback(
            function () use (&$pollCalls) {
                $pollCalls++;
                if ($pollCalls === 1) {
                    throw new \RuntimeException('poll down');
                }
                return [];
            }
        );

        $messages = [];
        $logger->method('warning')->willReturnCallback(function ($msg) use (&$messages) {
            $messages[] = $msg;
        });

        $changes = [];
        $watcher->onChange(function ($snap) use (&$changes) {
            $changes[] = $snap;
        });

        $watcher->start();

        $this->assertSame([[]], $changes);
        $this->assertContains('Watcher blocking query failed for app/, falling back to polling: blocking down', $messages);
        $this->assertContains('Watcher polling failed for app/: poll down', $messages);
    }

    public function testCallbackExceptionIsLoggedAndDoesNotStopDispatch(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = new Watcher(new Kv($transport), 'app/', $dispatcher, $logger);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $watcher) {
                $calls++;
                if ($calls === 2) {
                    $watcher->stop();
                }
                return [
                    'headers' => ['X-Consul-Index' => '1'],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                ];
            }
        );

        $logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Watcher callback error: cb boom'));

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($event) {
                return $event instanceof ConfigChangedEvent
                    && $event->getPrefix() === 'app/'
                    && $event->getConfig() === ['app/x' => 'v'];
            }));

        $watcher->onChange(function () {
            throw new \RuntimeException('cb boom');
        });

        $watcher->start();
    }

    public function testDispatcherReceivesConfigChangedEvent(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = new Watcher(new Kv($transport), 'cfg/', $dispatcher);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $watcher) {
                $calls++;
                if ($calls === 2) {
                    $watcher->stop();
                }
                return [
                    'headers' => ['X-Consul-Index' => '2'],
                    'body'    => [['Key' => 'cfg/a', 'Value' => base64_encode('1')]],
                ];
            }
        );

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (ConfigChangedEvent $event) {
                return $event->getPrefix() === 'cfg/' && $event->getConfig() === ['cfg/a' => '1'];
            }));

        $watcher->start();
    }

    public function testBlockingFailureCountIsResetAfterRecovery(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = (new Watcher(new Kv($transport), 'app/'))->setPollInterval(0);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $watcher) {
                $calls++;
                if ($calls === 1) {
                    throw new \RuntimeException('first failure');
                }
                $watcher->stop();
                return [
                    'headers' => ['X-Consul-Index' => '42'],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                ];
            }
        );
        $transport->method('get')->willReturn([]);

        // First poll returns empty (snapshot change []), then blocking resumes immediately
        // because pollInterval=0 -> minPollCycles=0. After recovery the loop keeps blocking.
        $watcher->start();

        $this->assertSame(2, $calls);
    }
}
