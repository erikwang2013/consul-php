<?php

namespace Erikwang2013\Consul\Config;

use Erikwang2013\Consul\Api\Kv;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

class Watcher
{
    private Kv $kv;
    private string $prefix;
    private ?EventDispatcherInterface $dispatcher;
    private array $callbacks = [];
    private int $blockingWait = 30;
    private int $pollInterval = 10;
    private bool $running = false;

    public function __construct(
        Kv $kv,
        string $prefix,
        ?EventDispatcherInterface $dispatcher = null
    ) {
        $this->kv = $kv;
        $this->prefix = $prefix;
        $this->dispatcher = $dispatcher;
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
                    sleep($this->pollInterval);
                    $result = $this->kv->all($this->prefix);
                    $snapshot = $this->snapshot($result);
                    if ($snapshot !== $lastSnapshot) {
                        $lastSnapshot = $snapshot;
                        $this->notify($snapshot);
                    }

                    $pollSuccesses++;
                    if ($pollSuccesses >= 5) {
                        $usePolling = false;
                        $pollSuccesses = 0;
                    }
                } else {
                    try {
                        $result = $this->kv->all($this->prefix, [
                            'index' => $index,
                            'wait'  => "{$this->blockingWait}s",
                        ]);
                    } catch (Throwable $e) {
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
                sleep(1);
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
