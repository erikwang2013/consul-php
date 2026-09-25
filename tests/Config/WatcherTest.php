<?php

namespace Erikwang2013\Consul\Tests\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Config\ConfigChangedEvent;
use Erikwang2013\Consul\Config\Watcher;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;
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

    public function testSetPollIntervalRejectsValuesBelowOneSecond(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        foreach ([0, -1] as $invalid) {
            try {
                $watcher->setPollInterval($invalid);
                $this->fail("setPollInterval({$invalid}) 应当抛出 InvalidArgumentException");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('至少为 1 秒', $e->getMessage());
            }
        }
    }

    public function testSetBlockingWaitRejectsValuesBelowOneSecond(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        foreach ([0, -1] as $invalid) {
            try {
                $watcher->setBlockingWait($invalid);
                $this->fail("setBlockingWait({$invalid}) 应当抛出 InvalidArgumentException");
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('至少为 1 秒', $e->getMessage());
            }
        }
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

    public function testBlockingFailureFallsBackToPollingThenResumesAfterFiveSuccesses(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = (new NoSleepWatcher(new Kv($transport), 'app/'))->setPollInterval(1);

        $blockingCalls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$blockingCalls, $watcher) {
                $blockingCalls++;
                if ($blockingCalls === 1) {
                    throw new \RuntimeException('blocking query failed');
                }
                $watcher->stop();
                return [
                    'headers' => ['X-Consul-Index' => '42'],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                ];
            }
        );

        $pollCalls = 0;
        $transport->expects($this->exactly(5))
            ->method('get')
            ->with('/v1/kv/app/', ['recurse' => 'true'])
            ->willReturnCallback(
                function () use (&$pollCalls) {
                    $pollCalls++;
                    if ($pollCalls === 1) {
                        return []; // 瞬时空响应：不是"配置被清空"
                    }
                    return [['Key' => 'app/x', 'Value' => base64_encode('v')]];
                }
            );

        $changes = [];
        $watcher->onChange(function ($snap) use (&$changes) {
            $changes[] = $snap;
        });

        $watcher->start();

        $this->assertSame(5, $pollCalls);       // 连续 5 次成功
        $this->assertSame(2, $blockingCalls);   // 之后切回阻塞查询
        $this->assertSame([['app/x' => 'v']], $changes); // 空响应没有产生"被清空"回调
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
        $watcher = (new NoSleepWatcher(new Kv($transport), 'app/', null, $logger))->setPollInterval(1);

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
            function () use (&$pollCalls, $watcher) {
                $pollCalls++;
                if ($pollCalls === 1) {
                    throw new \RuntimeException('poll down');
                }
                if ($pollCalls === 3) {
                    $watcher->stop();
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

        $this->assertSame(3, $pollCalls);
        // 一次失败就把"连续成功"计数清零，所以凑不满 5 次，一直留在轮询
        $this->assertSame(1, $blockingCalls);
        $this->assertSame([], $changes);
        $this->assertContains('Watcher blocking query failed for app/ (连续 1 次), falling back to polling: blocking down', $messages);
        $this->assertContains('Watcher polling failed for app/: poll down', $messages);
    }

    public function testPollingRecoveryRequiresConsecutiveSuccesses(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = (new NoSleepWatcher(new Kv($transport), 'app/'))->setPollInterval(1);

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

        // 每第 5 次轮询失败一次：最多连续成功 4 次，永远够不到回切阈值
        $pollCalls = 0;
        $transport->method('get')->willReturnCallback(
            function () use (&$pollCalls, $watcher) {
                $pollCalls++;
                if ($pollCalls === 12) {
                    $watcher->stop();
                    return [];
                }
                if ($pollCalls % 5 === 0) {
                    throw new \RuntimeException('poll down');
                }
                return [];
            }
        );

        $watcher->start();

        $this->assertSame(12, $pollCalls);
        $this->assertSame(1, $blockingCalls); // 从未切回阻塞查询
    }

    public function testPollingNotFoundNotifiesConfigClearedOnce(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $watcher = (new NoSleepWatcher(new Kv($transport), 'app/'))->setPollInterval(1);

        $blockingCalls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$blockingCalls) {
                $blockingCalls++;
                if ($blockingCalls === 1) {
                    return [
                        'headers' => ['X-Consul-Index' => '7'],
                        'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                    ];
                }
                throw new \RuntimeException('blocking down');
            }
        );

        // 前缀被删空：Consul 对无键前缀返回 404
        $pollCalls = 0;
        $transport->method('get')->willReturnCallback(
            function () use (&$pollCalls, $watcher) {
                $pollCalls++;
                if ($pollCalls === 3) {
                    $watcher->stop();
                }
                throw new NotFoundException('app/');
            }
        );

        $changes = [];
        $watcher->onChange(function ($snap) use (&$changes) {
            $changes[] = $snap;
        });

        $watcher->start();

        // 只有"从有到无"这一次变更，后续 404 不再重复通知
        $this->assertSame([['app/x' => 'v'], []], $changes);
        $this->assertSame(3, $pollCalls);
    }

    public function testSnapshotFailureIsLoggedAndDoesNotStopWatcher(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $watcher = new NoSleepWatcher(new Kv($transport), 'app/', null, $logger);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $watcher) {
                $calls++;
                if ($calls === 1) {
                    // 脏数据：条目不是数组，decodeValue() 抛 TypeError
                    return ['headers' => ['X-Consul-Index' => '1'], 'body' => ['v']];
                }
                $watcher->stop();
                return [
                    'headers' => ['X-Consul-Index' => '2'],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode('v')]],
                ];
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

        $this->assertSame(2, $calls); // 快照失败没有把监听循环带走
        $this->assertSame([['app/x' => 'v']], $changes);
        $this->assertNotEmpty(array_filter($messages, function ($msg) {
            return strpos($msg, 'Watcher snapshot failed for app/') === 0;
        }));
    }

    public function testDispatcherExceptionIsLoggedAndDoesNotStopWatcher(): void
    {
        $transport = $this->createMock(TransportInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $watcher = new NoSleepWatcher(new Kv($transport), 'app/', $dispatcher, $logger);

        $calls = 0;
        $transport->method('getWithHeaders')->willReturnCallback(
            function () use (&$calls, $watcher) {
                $calls++;
                if ($calls === 2) {
                    $watcher->stop();
                }
                return [
                    'headers' => ['X-Consul-Index' => (string) $calls],
                    'body'    => [['Key' => 'app/x', 'Value' => base64_encode("v{$calls}")]],
                ];
            }
        );

        $dispatcher->method('dispatch')->willThrowException(new \RuntimeException('listener boom'));

        $messages = [];
        $logger->method('warning')->willReturnCallback(function ($msg) use (&$messages) {
            $messages[] = $msg;
        });

        $watcher->start();

        $this->assertSame(2, $calls); // 分发异常没有把监听循环带走
        $this->assertContains('Watcher dispatcher error: listener boom', $messages);
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
}

/**
 * 不真正 sleep 的 Watcher：轮询间隔的下界是 1 秒，直接跑会把测试拖成秒级等待。
 */
class NoSleepWatcher extends Watcher
{
    protected function sleep(int $seconds): void
    {
    }
}
