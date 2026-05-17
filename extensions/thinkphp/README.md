# erikwang/consul-php-thinkphp

ThinkPHP 集成包，通过 Service 类的 `bind` 机制注册到容器。

## 安装

```bash
composer require erikwang/consul-php-thinkphp
composer require guzzlehttp/guzzle php-http/guzzle7-adapter
```

## 配置

复制配置到 `config/consul.php`：

```php
<?php

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

## 注册 Service

在 `app/service` 目录下创建 `ConsulService.php`，并在全局中间件或 `AppService` 中注册：

```php
// app\service\ConsulService.php
namespace app\service;

use Erikwang2013\Consul\Thinkphp\ConsulService as BaseConsulService;

class ConsulService extends BaseConsulService
{
}
```

或在 `app\AppService.php` 中直接绑定：

```php
namespace app;

use think\Service as BaseService;
use Erikwang2013\Consul\Client\ConsulClient;

class AppService extends BaseService
{
    public function register(): void
    {
        $this->app->bind('consul', function () {
            $config = $this->app->config->get('consul', []);
            return new ConsulClient($config);
        });
    }
}
```

## 使用

### 依赖注入 / 容器获取

```php
namespace app\controller;

use Erikwang2013\Consul\Client\ConsulClient;
use think\facade\App;

class UserController
{
    public function index()
    {
        /** @var ConsulClient $consul */
        $consul = App::get('consul');
        $instances = $consul->serviceDiscovery()->healthyInstances('user-service');

        return json($instances);
    }
}
```

### 助手函数

定义一个全局助手函数：

```php
// app/common.php
use think\facade\App;

function consul(): \Erikwang2013\Consul\Client\ConsulClient
{
    return App::get('consul');
}

// 使用
$services = consul()->catalog->services();
```

### 服务注册（通常放在事件监听中）

```php
// app/listener/ConsulRegister.php
namespace app\listener;

use think\facade\App;

class ConsulRegister
{
    public function handle(): void
    {
        /** @var \Erikwang2013\Consul\Client\ConsulClient $consul */
        $consul = App::get('consul');

        $consul->serviceRegistry()->register(
            name: 'thinkphp-app',
            address: gethostname(),
            port: 80,
            options: [
                'id'    => 'tp-' . gethostname(),
                'tags'  => [env('app_env', 'dev')],
                'check' => [
                    'http'     => 'http://' . $_SERVER['HTTP_HOST'] . '/health',
                    'interval' => '10s',
                ],
            ]
        );
    }
}
```

### 配置中心

```php
$config = consul()->configCenter();
$debug = $config->get('app/debug', false);
$all = $config->namespace('app/');
```

### 配置热更新

```php
// 在 Swoole 自定义进程或 Timer 中运行
\think\facade\App::get('consul')->configCenter()
    ->watch('app/')
    ->onChange(function (array $config) {
        \think\facade\Log::info('Consul config changed', $config);
    })
    ->start();
```

## 最低要求

- PHP 8.0+
- ThinkPHP 6.0 / 8.0
