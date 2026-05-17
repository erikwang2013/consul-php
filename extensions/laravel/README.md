# erikwang2013/consul-php-laravel

Laravel 集成包，自动注册 `ConsulClient` 到容器并提供 Facade。

## 安装

```bash
composer require erikwang2013/consul-php-laravel
composer require guzzlehttp/guzzle php-http/guzzle7-adapter
```

## 配置

发布配置文件：

```bash
php artisan vendor:publish --tag=consul-config
```

`config/consul.php`：

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

`.env`：

```
CONSUL_BASE_URI=http://consul-server:8500
CONSUL_TOKEN=your-acl-token
```

## 使用

### 依赖注入

```php
use Erikwang2013\Consul\Client\ConsulClient;

class UserController extends Controller
{
    public function index(ConsulClient $consul)
    {
        $instances = $consul->serviceDiscovery()->healthyInstances('user-service');
        return response()->json($instances);
    }
}
```

### Facade

```php
use Consul;

$services = Consul::catalog->services();
$config = Consul::configCenter()->get('app/debug');
```

### 服务注册（通常放在 AppServiceProvider::boot）

```php
use Erikwang2013\Consul\Client\ConsulClient;

class AppServiceProvider extends ServiceProvider
{
    public function boot(ConsulClient $consul): void
    {
        $consul->serviceRegistry()->register(
            name: 'laravel-app',
            address: gethostname(),
            port: 80,
            options: [
                'id'    => 'laravel-' . gethostname(),
                'tags'  => [app()->environment()],
                'check' => [
                    'http'     => url('/health'),
                    'interval' => '10s',
                ],
            ]
        );
    }
}
```

### 配置中心 + 缓存

Laravel 扩展默认注入 `CacheInterface`，`configCenter()->get()` 和 `namespace()` 自动走缓存：

```php
$consul->configCenter()->get('app/db_host'); // 首次读 Consul，后续走缓存
```

### 配置热更新

```php
// routes/console.php — Artisan 命令启动 Watcher
Artisan::command('consul:watch', function (ConsulClient $consul) {
    $watcher = $consul->configCenter()->watch('app/');
    $watcher->onChange(function (array $config) {
        Log::info('Consul config changed', $config);
    });
    $watcher->start();
})->purpose('Watch Consul config changes');

// 启动: php artisan consul:watch
```

### 事件监听

```php
// EventServiceProvider.php
use Erikwang2013\Consul\Config\ConfigChangedEvent;

protected $listen = [
    ConfigChangedEvent::class => [
        \App\Listeners\ReloadAppConfig::class,
    ],
];
```

## 最低要求

- PHP 8.0+
- Laravel 8 / 9 / 10 / 11 / 12
- PSR-18 HTTP Client（默认通过 Guzzle + php-http 适配）
