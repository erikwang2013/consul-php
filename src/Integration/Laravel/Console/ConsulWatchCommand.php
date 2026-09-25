<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Laravel\Console;

use Erikwang2013\Consul\Client\ConsulClient;
use Illuminate\Console\Command;

/**
 * 监听 Consul KV 变更并把变化输出到控制台。
 *
 * 需要常驻进程：`php artisan consul:watch`，配置前缀取自 `consul.watch_prefix`。
 * 真正的配置热更新由 Consul\Config\Watcher 完成（blocking query + 异常降级轮询），
 * 命令只是把它挂到 Artisan 上。
 */
class ConsulWatchCommand extends Command
{
    protected $signature = 'consul:watch {prefix? : 监听的 KV 前缀，默认取 consul.watch_prefix}';

    protected $description = '监听 Consul 配置变更（热更新）';

    public function handle(ConsulClient $client): int
    {
        $prefix = $this->argument('prefix') ?: config('consul.watch_prefix', 'app/');
        $watcher = $client->configCenter()->watch($prefix);

        $watcher->onChange(function (array $updated) use ($prefix): void {
            $this->info(sprintf('[%s] 配置已更新：%s', date('Y-m-d H:i:s'), implode(', ', array_keys($updated))));
        });

        $this->info("开始监听 Consul 前缀 [{$prefix}]，按 Ctrl+C 退出。");

        $watcher->start();

        return self::SUCCESS;
    }
}
