<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Transport\TransportInterface;
use InvalidArgumentException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class Watcher
{
    /** 阻塞查询失败后，轮询需要"连续"成功多少次才切回阻塞查询 */
    private const RECOVERY_CYCLES = 5;

    private Kv $kv;
    private TransportInterface $transport;
    private string $prefix;
    private ?EventDispatcherInterface $dispatcher;
    private LoggerInterface $logger;
    private array $callbacks = [];
    private int $blockingWait = 30;
    private int $pollInterval = 10;
    private bool $running = false;
    private int $blockingFailures = 0;

    public function __construct(
        Kv $kv,
        string $prefix,
        ?EventDispatcherInterface $dispatcher = null,
        ?LoggerInterface $logger = null
    ) {
        $this->kv = $kv;
        $this->transport = $kv->getTransport();
        $this->prefix = $prefix;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger ?? new NullLogger();
    }

    public function onChange(callable $callback): self
    {
        $this->callbacks[] = $callback;
        return $this;
    }

    public function setBlockingWait(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException(
                "阻塞查询的 wait 必须至少为 1 秒：Consul 会忽略非正的 wait 并退回默认的 5 分钟持有，客户端必然超时（收到 {$seconds}）"
            );
        }
        $this->blockingWait = $seconds;
        return $this;
    }

    public function setPollInterval(int $seconds): self
    {
        if ($seconds < 1) {
            throw new InvalidArgumentException(
                "轮询间隔必须至少为 1 秒：0 会在故障时无退避忙等打爆 Consul，负数会让 sleep() 抛 ValueError 直接终止监听（收到 {$seconds}）"
            );
        }
        $this->pollInterval = $seconds;
        return $this;
    }

    public function start(): void
    {
        $this->running = true;
        $index = 0;
        $lastSnapshot = null;
        $usePolling = false;
        $pollSuccesses = 0;
        $path = '/v1/kv/' . $this->kv->encodeKey($this->prefix);
        $wait = "{$this->blockingWait}s";

        while ($this->running) {
            if ($usePolling) {
                try {
                    $result = $this->kv->all($this->prefix);
                    $pollSuccesses++;

                    // 成功的空数组不作为变更信号：Consul 对"前缀下无键"返回的是 404（见下面的 catch），
                    // 200 + 空数组只能是瞬时的空响应（代理/中间层的退化响应），据此回调会把抖动误报成"配置被清空"。
                    // 取舍：若某个网关把"前缀为空"表现为 200 + 空数组，删除事件会漏报——宁可漏报，也不误报清空。
                    if ($result !== []) {
                        $snapshot = $this->snapshot($result);
                        if ($snapshot !== null && $snapshot !== $lastSnapshot) {
                            $lastSnapshot = $snapshot;
                            $this->notify($snapshot);
                        }
                    }
                } catch (NotFoundException) {
                    // 前缀下已无任何键（真的被删空）→ 这才是"从有到无"的变更
                    $pollSuccesses++;
                    if ($lastSnapshot !== null && $lastSnapshot !== []) {
                        $lastSnapshot = [];
                        $this->notify([]);
                    }
                } catch (Throwable $e) {
                    // 轮询自身失败："连续成功"被打断，重新计数
                    $pollSuccesses = 0;
                    $this->logger->warning("Watcher polling failed for {$this->prefix}: " . $e->getMessage());
                }

                if ($pollSuccesses >= self::RECOVERY_CYCLES) {
                    $usePolling = false;
                    $pollSuccesses = 0;
                }

                $this->sleep($this->pollInterval);
            } else {
                try {
                    $response = $this->transport->getWithHeaders($path, [
                        'recurse' => 'true',
                        'index'   => $index,
                        'wait'    => $wait,
                    ]);
                    $index = (int) ($response['headers']['X-Consul-Index'] ?? $index);
                    $result = $response['body'];
                    $this->blockingFailures = 0;
                } catch (Throwable $e) {
                    $this->blockingFailures++;
                    $this->logger->warning("Watcher blocking query failed for {$this->prefix} (连续 {$this->blockingFailures} 次), falling back to polling: " . $e->getMessage());
                    $usePolling = true;
                    $pollSuccesses = 0;
                    continue;
                }

                // 快照构造与回调的异常都不会冲出循环（snapshot()/notify() 内部已兜住并记日志）
                $snapshot = $this->snapshot($result);
                if ($snapshot !== null && $snapshot !== $lastSnapshot) {
                    $lastSnapshot = $snapshot;
                    $this->notify($snapshot);
                }
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * 轮询间隔的等待，抽成方法以便测试替换掉真实等待。
     */
    protected function sleep(int $seconds): void
    {
        \sleep($seconds);
    }

    /**
     * 构造前缀下的键值快照。条目结构异常时记日志并返回 null（调用方保持上一次快照），
     * 不让一条脏数据把整个监听循环带走。
     */
    private function snapshot(array $kvResult): ?array
    {
        try {
            $snap = [];
            foreach ($kvResult as $item) {
                $snap[$item['Key'] ?? ''] = $this->kv->decodeValue($item);
            }
            ksort($snap);
            return $snap;
        } catch (Throwable $e) {
            $this->logger->warning("Watcher snapshot failed for {$this->prefix}: " . $e->getMessage());
            return null;
        }
    }

    private function notify(array $snapshot): void
    {
        foreach ($this->callbacks as $cb) {
            try {
                $cb($snapshot);
            } catch (Throwable $e) {
                $this->logger->error("Watcher callback error: " . $e->getMessage());
            }
        }

        if ($this->dispatcher) {
            try {
                $this->dispatcher->dispatch(new ConfigChangedEvent($this->prefix, $snapshot));
            } catch (Throwable $e) {
                $this->logger->warning("Watcher dispatcher error: " . $e->getMessage());
            }
        }
    }
}
