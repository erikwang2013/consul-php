<?php

namespace Erikwang\Consul\Config;

use Erikwang\Consul\Api\Kv;
use Psr\EventDispatcher\EventDispatcherInterface;

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
                } else {
                    try {
                        $result = $this->kv->all($this->prefix, [
                            'index' => $index,
                            'wait'  => "{$this->blockingWait}s",
                        ]);
                    } catch (\Throwable $e) {
                        $usePolling = true;
                        continue;
                    }

                    $snapshot = $this->snapshot($result);
                    if ($snapshot !== $lastSnapshot) {
                        $lastSnapshot = $snapshot;
                        $this->notify($snapshot);
                    }
                }
            } catch (\Throwable $e) {
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
            $value = base64_decode($item['Value'] ?? '', true) ?: $item['Value'] ?? '';
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
