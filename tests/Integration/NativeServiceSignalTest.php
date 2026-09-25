<?php

namespace Erikwang2013\Consul\Tests\Integration;

use Erikwang2013\Consul\Tests\Support\Subprocess;
use PHPUnit\Framework\TestCase;

/**
 * NativeService 的"进程退出自动注销"：装了 pcntl 时 SIGTERM/SIGINT 要先注销再退出，
 * 没装时靠 register_shutdown_function。两件事都只能在子进程里验证。
 */
class NativeServiceSignalTest extends TestCase
{
    private const SERVICE_SCRIPT = <<<'PHP'
        <?php
        namespace ConsulNativeServiceCheck;

        require $argv[1];

        use Erikwang2013\Consul\Integration\Native\NativeService;
        use Erikwang2013\Consul\Tests\Support\RegistryStub;

        $service = NativeService::start(new RegistryStub(), 'web', '10.0.0.1', 8080, ['id' => 'web-1'], 1);

        echo "started\n";
        flush();

        if (($argv[2] ?? '') === 'serve') {
            $service->serve();
        }
        PHP;

    public function testNormalExitDeregistersThroughShutdownHook(): void
    {
        $result = Subprocess::run(self::SERVICE_SCRIPT);

        $this->assertSame(0, $result['status'], $result['output']);
        $this->assertStringContainsString('register:web-1', $result['output']);
        $this->assertStringContainsString('deregister:web-1', $result['output']);
    }

    public function testSigtermDeregistersBeforeExiting(): void
    {
        if (!\extension_loaded('pcntl') || !\defined('SIGTERM')) {
            $this->markTestSkipped('需要 pcntl 扩展才能验证信号处理');
        }

        $file = tempnam(sys_get_temp_dir(), 'consul-signal') . '.php';
        $log = tempnam(sys_get_temp_dir(), 'consul-signal-log');
        file_put_contents($file, self::SERVICE_SCRIPT);

        $process = proc_open(
            [PHP_BINARY, $file, Subprocess::autoloadPath(), 'serve'],
            [
                0 => ['file', '/dev/null', 'r'],
                1 => ['file', $log, 'w'],
                2 => ['file', $log, 'a'],
            ],
            $pipes
        );

        if (!is_resource($process)) {
            $this->fail('无法启动子进程');
        }

        try {
            // 第一次心跳说明 serve() 已经进循环，信号处理器此时必定装好了
            if (!$this->waitFor($log, 'heartbeat:web-1')) {
                proc_terminate($process, SIGKILL);
                proc_close($process);
                $this->fail('子进程没有进入心跳循环：' . file_get_contents($log));
            }

            proc_terminate($process, SIGTERM);
            $status = proc_close($process);
            $output = (string) file_get_contents($log);
        } finally {
            @unlink($file);
            @unlink($log);
        }

        $this->assertSame(0, $status, "SIGTERM 应该走优雅退出，实际日志：\n$output");
        $this->assertStringContainsString('deregister:web-1', $output);
    }

    private function waitFor(string $log, string $needle, float $timeout = 5.0): bool
    {
        $deadline = microtime(true) + $timeout;

        do {
            if (strpos((string) @file_get_contents($log), $needle) !== false) {
                return true;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);

        return false;
    }
}
