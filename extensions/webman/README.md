# erikwang/consul-php-webman

webman 插件包，通过 webman 插件系统自动化安装。

## 安装

```bash
composer require erikwang/consul-php-webman
composer require guzzlehttp/guzzle php-http/guzzle7-adapter
```

安装后自动复制配置文件到 `config/plugin/erikwang/consul-php/app.php`。

## 配置

`config/plugin/erikwang/consul-php/app.php`：

```php
return [
    'enable'   => true,
    'base_uri' => getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500',
    'token'    => getenv('CONSUL_TOKEN') ?: '',
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
];
```

## 使用

### 手动创建客户端

```php
use Erikwang2013\Consul\Client\ConsulClient;

$consul = new ConsulClient(['base_uri' => 'http://127.0.0.1:8500']);
```

### 服务注册（在进程启动时）

```php
// process/ConsulRegister.php
namespace process;

use Erikwang2013\Consul\Client\ConsulClient;
use Workerman\Worker;

class ConsulRegister
{
    public function onWorkerStart(Worker $worker): void
    {
        $consul = new ConsulClient([
            'base_uri' => getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500',
        ]);

        $consul->serviceRegistry()->register(
            name: 'webman-app',
            address: getenv('APP_HOST') ?: '127.0.0.1',
            port: (int) (getenv('APP_PORT') ?: 8787),
            options: [
                'id'    => 'webman-' . getenv('APP_HOST'),
                'tags'  => ['webman'],
                'check' => [
                    'http'     => 'http://' . (getenv('APP_HOST') ?: '127.0.0.1') . ':' . (getenv('APP_PORT') ?: 8787) . '/health',
                    'interval' => '10s',
                ],
            ]
        );
    }
}
```

在 `config/process.php` 中注册：

```php
return [
    'consul_register' => [
        'handler' => \process\ConsulRegister::class,
    ],
];
```

### 服务发现

```php
$consul = new ConsulClient(['base_uri' => 'http://127.0.0.1:8500']);
$instances = $consul->serviceDiscovery()->healthyInstances('user-service');

// 负载均衡
$instance = $consul->serviceDiscovery()->selectInstance('user-service');
if ($instance) {
    $url = "http://{$instance['address']}:{$instance['port']}/api/endpoint";
}
```

### 配置中心

```php
$config = $consul->configCenter();
$dbHost = $config->get('app/db_host', 'localhost');
```

### 配合 webman Redis 缓存

```php
use Workerman\Redis;

$redis = new Redis();
// 实现 PSR-16 CacheInterface 包装 Redis 实例
$psr16Cache = new RedisCacheAdapter($redis);

$discovery = $consul->serviceDiscovery($health, $psr16Cache, 300);
```

## 卸载

```bash
composer remove erikwang/consul-php-webman
```

## 最低要求

- PHP 8.0+
- webman 1.0+
