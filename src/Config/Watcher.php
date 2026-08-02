<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Transport\TransportInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

class Watcher
{
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
        $this->blockingWait = $seconds;
        return $this;
    }

    public function setPollInterval(int $seconds): self
    {
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

        while ($this->running) {
            try {
                if ($usePolling) {
                    /** @phpstan-ignore-next-line */
                    if (!$this->running) break;
                    $result = $this->kv->all($this->prefix);
                    $snapshot = $this->snapshot($result);
                    if ($snapshot !== $lastSnapshot) {
                        $lastSnapshot = $snapshot;
                        $this->notify($snapshot);
                    }

                    $pollSuccesses++;
                    $minPollCycles = $this->blockingFailures > 0
                        ? $this->pollInterval * min($this->blockingFailures, 5)
                        : $this->pollInterval;
                    if ($pollSuccesses >= $minPollCycles) {
                        $usePolling = false;
                        $pollSuccesses = 0;
                    }
                } else {
                    try {
                        $response = $this->transport->getWithHeaders('/v1/kv/' . rawurlencode($this->prefix), [
                            'recurse' => 'true',
                            'index'   => $index,
                            'wait'    => "{$this->blockingWait}s",
                        ]);
                        $index = (int) ($response['headers']['X-Consul-Index'] ?? $index);
                        $result = $response['body'];
                        $this->blockingFailures = 0;
                    } catch (Throwable $e) {
                        $this->blockingFailures++;
                        $this->logger->warning("Watcher blocking query failed for {$this->prefix}, falling back to polling: " . $e->getMessage());
                        $usePolling = true;
                        $pollSuccesses = 0;
                        continue;
                    }

                    $snapshot = $this->snapshot($result);
                    if ($snapshot !== $lastSnapshot) {
                        $lastSnapshot = $snapshot;
                        $this->notify($snapshot);
                    }
                }
            } catch (Throwable $e) {
                $this->logger->error("Watcher error for {$this->prefix}: " . $e->getMessage());
                if (!$this->running) break;
                }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function snapshot(array $kvResult): array
    {
        $snap = [];
        foreach ($kvResult as $item) {
            $key = $item['Key'] ?? '';
            $decoded = base64_decode($item['Value'] ?? '', true);
            $value = $decoded !== false ? $decoded : ($item['Value'] ?? '');
            $snap[$key] = $value;
        }
        ksort($snap);
        return $snap;
    }

    private function notify(array $snapshot): void
    {
        foreach ($this->callbacks as $cb) {
            $cb($snapshot);
        }

        if ($this->dispatcher) {
            $this->dispatcher->dispatch(new ConfigChangedEvent($this->prefix, $snapshot));
        }
    }
}
