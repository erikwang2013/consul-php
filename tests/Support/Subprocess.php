<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Tests\Support;

use RuntimeException;

/**
 * 在隔离子进程里跑一段脚本，用于无法在本进程内造出的状态：
 * 自动发现失败、信号处理、shutdown 钩子等。
 *
 * 脚本里用 `require $argv[1];` 拿到 composer 的自动加载器，其余参数从 $argv[2] 起。
 */
final class Subprocess
{
    private static $autoload;

    /** 供需要自己 proc_open（例如要发信号）的测试使用。 */
    public static function autoloadPath(): string
    {
        return self::$autoload ??= (string) realpath(__DIR__ . '/../../vendor/autoload.php');
    }

    /**
     * @param array<int, string> $arguments 追加给脚本的参数（对应 $argv[2..]）
     *
     * @return array{status: int, output: string} 退出码与合并后的 stdout/stderr
     */
    public static function run(string $script, array $arguments = []): array
    {
        $file = tempnam(sys_get_temp_dir(), 'consul-test') . '.php';
        $log = tempnam(sys_get_temp_dir(), 'consul-test-log');
        file_put_contents($file, $script);

        $command = array_merge([PHP_BINARY, $file, self::autoloadPath()], $arguments);
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $log, 'w'],
            2 => ['file', $log, 'a'],
        ];

        try {
            $process = proc_open($command, $descriptors, $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('无法启动子进程');
            }

            $status = proc_close($process);
            $output = (string) file_get_contents($log);
        } finally {
            @unlink($file);
            @unlink($log);
        }

        return ['status' => $status, 'output' => $output];
    }
}
