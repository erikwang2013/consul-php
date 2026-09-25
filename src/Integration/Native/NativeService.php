<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Native;

use Erikwang2013\Consul\Service\Registry;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * 原生 PHP 的服务生命周期助手：把「注册 → TTL 心跳 → 优雅下线」收成一行。
 *
 * 其他框架有进程生命周期可以挂：Laravel 的 Artisan 命令、Hyperf 的 AbstractProcess、
 * webman 的 onWorkerStart、ThinkPHP 的 Timer——裸 PHP 没有，这个类补的就是这块。
 *
 * ```php
 * $service = NativeService::start($client->serviceRegistry(), 'my-app', '10.0.0.1', 8080, [
 *     'check' => ['ttl' => '30s', 'deregister_critical_service_after' => '120s'],
 * ], heartbeatInterval: 10);
 *
 * $service->serve();   // 阻塞发心跳，退出时自动注销
 * ```
 */
final class NativeService
{
    private Registry $registry;

    private string $serviceId;

    private int $interval;

    private ?LoggerInterface $logger;

    private bool $running = false;

    private bool $registered = false;

    private function __construct(Registry $registry, string $serviceId, int $interval, ?LoggerInterface $logger)
    {
        $this->registry = $registry;
        $this->serviceId = $serviceId;
        $this->interval = max(1, $interval);
        $this->logger = $logger;
    }

    /**
     * 注册服务，并挂上「进程退出自动注销」的钩子。
     *
     * @param array<string, mixed> $options 与 Registry::register() 相同的选项（id / tags / meta / check 等）
     */
    public static function start(
        Registry $registry,
        string $name,
        string $address,
        int $port,
        array $options = [],
        int $heartbeatInterval = 10,
        ?LoggerInterface $logger = null
    ): self {
        $serviceId = (string) ($options['id'] ?? sprintf('%s-%s:%d', $name, $address, $port));
        $registry->register($name, $address, $port, $options + ['id' => $serviceId]);

        $service = new self($registry, $serviceId, $heartbeatInterval, $logger);
        $service->registered = true;

        // 脚本结束（正常退出或致命错误）时注销，避免留下僵尸实例
        register_shutdown_function([$service, 'stop']);

        return $service;
    }

    /** 阻塞式心跳循环，适合常驻脚本；装了 pcntl 时 Ctrl+C / SIGTERM 会先注销再退出。 */
    public function serve(): void
    {
        $this->installSignalHandlers();
        $this->logger?->info("Consul 服务 {$this->serviceId} 开始心跳，间隔 {$this->interval}s");

        $this->running = true;
        while ($this->running) {
            $this->heartbeat();
            sleep($this->interval);
        }
    }

    /** 发一次心跳；自己控制循环（如配合 Timer）时用这个。 */
    public function heartbeat(string $note = ''): void
    {
        try {
            $this->registry->heartbeat($this->serviceId, $note);
        } catch (Throwable $e) {
            // 心跳失败不该拖垮业务进程：Consul 侧会按 deregister_critical_service_after 自行判定
            $this->logger?->warning("Consul 心跳失败（{$this->serviceId}）：" . $e->getMessage());
        }
    }

    /** 注销服务并停止循环；重复调用安全。 */
    public function stop(): void
    {
        $this->running = false;

        if (!$this->registered) {
            return;
        }

        $this->registered = false;

        try {
            $this->registry->deregister($this->serviceId);
            $this->logger?->info("Consul 服务 {$this->serviceId} 已注销");
        } catch (Throwable $e) {
            $this->logger?->warning("Consul 注销失败（{$this->serviceId}）：" . $e->getMessage());
        }
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    private function installSignalHandlers(): void
    {
        if (!\function_exists('pcntl_signal') || !\function_exists('pcntl_async_signals')) {
            return;   // 没装 pcntl 时依赖 register_shutdown_function + deregister_critical_service_after
        }

        pcntl_async_signals(true);
        foreach ([SIGINT, SIGTERM] as $signal) {
            pcntl_signal($signal, function () use ($signal): void {
                $this->logger?->info("收到信号 {$signal}，正在注销 {$this->serviceId}");
                $this->stop();
                exit(0);
            });
        }
    }
}
