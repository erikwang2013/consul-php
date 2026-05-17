# erikwang/consul-php-hyperf

Hyperf 集成包，自动注册 `ConsulClient` 到依赖注入容器，支持 Swoole 协程。

## 安装

```bash
composer require erikwang/consul-php-hyperf
```

## 配置

发布配置文件：

```bash
php bin/hyperf.php vendor:publish consul
```

`config/autoload/consul.php`：

```php
return [
    'base_uri' => env('CONSUL_BASE_URI', 'http://127.0.0.1:8500'),
    'token'    => env('CONSUL_TOKEN', ''),
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
];
```

## 使用

### 依赖注入

```php
use Erikwang2013\Consul\Client\ConsulClient;
use Hyperf\Di\Annotation\Inject;

class UserController extends AbstractController
{
    #[Inject]
    private ConsulClient $consul;

    public function index()
    {
        $instances = $this->consul->serviceDiscovery()->healthyInstances('user-service');
        return $this->response->json($instances);
    }
}
```

### 通过容器获取

```php
$consul = $this->container->get(\Erikwang2013\Consul\Client\ConsulClient::class);
$consul->kv->put('key', 'value');
```

### 服务注册（协程中自动使用 Swoole 协程客户端）

```php
// AppServiceProvider 或 Listener
class ServiceRegisterListener implements ListenerInterface
{
    public function listen(): array
    {
        return [MainServerStart::class];
    }

    public function process(object $event): void
    {
        $consul = $this->container->get(ConsulClient::class);

        $consul->serviceRegistry()->register(
            name: 'hyperf-app',
            address: env('APP_HOST', '127.0.0.1'),
            port: (int) env('HTTP_PORT', 9501),
            options: [
                'id'    => 'hyperf-' . env('APP_HOST'),
                'tags'  => [env('APP_ENV', 'dev')],
                'check' => [
                    'http'     => 'http://' . env('APP_HOST') . ':' . env('HTTP_PORT', 9501) . '/health',
                    'interval' => '10s',
                ],
            ]
        );
    }
}
```

### 协程环境下配置热更新

```php
// ConfigChangedListener.php — 启动时在协程中运行
use Hyperf\Process\AbstractProcess;

class ConsulWatchProcess extends AbstractProcess
{
    public function handle(): void
    {
        $consul = $this->container->get(ConsulClient::class);
        $watcher = $consul->configCenter()->watch('app/');
        $watcher->onChange(function (array $config) {
            // 更新本地配置
        });
        $watcher->start();
    }
}
```

### 配合 Hyperf 缓存

```php
// Hyperf 默认使用 PSR-16，configCenter 自动走缓存
$consul->configCenter()->get('app/db_host', 'default');
```

## 协程注意事项

- `ConsulAsyncClient` + `Promise::wait()` 在协程中会阻塞当前协程但不阻塞整个进程
- 建议在高并发场景下使用协程 HTTP 客户端以获得最佳性能
- 默认由 `ConsulClientFactory` 注入 PSR-18 客户端，Hyperf 可通过配置绑定为协程客户端

## 最低要求

- PHP 8.0+
- Hyperf 2.0 / 3.0
- Swoole
