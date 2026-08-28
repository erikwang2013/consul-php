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
        $lastFingerprint = null;
        $usePolling = false;
        $pollSuccesses = 0;
        $path = '/v1/kv/' . $this->kv->encodeKey($this->prefix);
        $wait = "{$this->blockingWait}s";

        while ($this->running) {
            if ($usePolling) {
                try {
                    $result = $this->kv->all($this->prefix);
                    $fingerprint = md5(serialize($result));
                    if ($fingerprint !== $lastFingerprint) {
                        $lastFingerprint = $fingerprint;
                        $snapshot = $this->snapshot($result);
                        if ($snapshot !== $lastSnapshot) {
                            $lastSnapshot = $snapshot;
                            $this->notify($snapshot);
                        }
                    }

                    $pollSuccesses++;
                    $minPollCycles = $this->blockingFailures > 0
                        ? $this->pollInterval * min($this->blockingFailures, 5)
                        : $this->pollInterval;
                    if ($pollSuccesses >= $minPollCycles) {
                        $usePolling = false;
                        $pollSuccesses = 0;
                    }
                } catch (Throwable $e) {
                    $this->logger->warning("Watcher polling failed for {$this->prefix}: " . $e->getMessage());
                }
                sleep($this->pollInterval);
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
            $snap[$item['Key'] ?? ''] = $this->kv->decodeValue($item);
        }
        ksort($snap);
        return $snap;
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
            $this->dispatcher->dispatch(new ConfigChangedEvent($this->prefix, $snapshot));
        }
    }
}
