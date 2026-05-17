# erikwang2013/consul-php

PHP Consul 客户端，完整覆盖 Consul HTTP API v1，重点支持服务注册发现与配置中心。核心包零框架依赖，通过独立扩展包适配各框架。

PHP 8.0+ · PSR-18/PSR-3/PSR-14/PSR-16 · 零框架依赖

---

## 文档导航

| 文档 | 链接 |
|------|------|
| **文档总目录** | [docs/README.md](docs/README.md) |
| **Laravel 集成** | [extensions/laravel/README.md](extensions/laravel/README.md) |
| **Hyperf 集成** | [extensions/hyperf/README.md](extensions/hyperf/README.md) |
| **webman 集成** | [extensions/webman/README.md](extensions/webman/README.md) |
| **ThinkPHP 集成** | [extensions/thinkphp/README.md](extensions/thinkphp/README.md) |
| **设计文档** | [docs/superpowers/specs/2026-05-14-consul-php-design.md](docs/superpowers/specs/2026-05-14-consul-php-design.md) |

---

## 框架集成一览

| | Laravel | Hyperf | webman | ThinkPHP |
|---|---|---|---|---|
| **扩展包** | `consul-php-laravel` | `consul-php-hyperf` | `consul-php-webman` | `consul-php-thinkphp` |
| **注入方式** | 自动发现 + `ServiceProvider` | 自动发现 + `ConfigProvider` | 手动 `new` / 插件 | 手动 `bind` 到容器 |
| **便捷访问** | `Consul` Facade | `#[Inject]` 注解 | — | `app('consul')` 助手 |
| **配置位置** | `config/consul.php` | `config/autoload/consul.php` | `config/plugin/erikwang2013/consul-php/app.php` | `config/consul.php` |
| **HTTP 客户端** | Guzzle (PSR-18) | Swoole 协程客户端 | Guzzle (PSR-18) | Guzzle (PSR-18) |
| **缓存** | Laravel Cache (PSR-16) | Hyperf Cache (PSR-16) | 自行注入 | 自行注入 |
| **热更新运行** | Artisan 命令 | `AbstractProcess` 协程 | `Worker` 进程 | Timer / Swoole 进程 |
| **事件监听** | `EventServiceProvider` | Hyperf Event | — | ThinkPHP Listener |
| **文档** | [README](extensions/laravel/README.md) | [README](extensions/hyperf/README.md) | [README](extensions/webman/README.md) | [README](extensions/thinkphp/README.md) |

### 同一操作，不同写法

**获取客户端：**

| 框架 | 写法 |
|------|------|
| 通用 | `$client = new ConsulClient(['base_uri' => '...']);` |
| Laravel | `$client = app(ConsulClient::class);` 或 `Consul::kv->get(...)` |
| Hyperf | `#[Inject] private ConsulClient $consul;` |
| webman | `$client = new ConsulClient(['base_uri' => '...']);` |
| ThinkPHP | `$client = app('consul');` |

**服务注册：**

```php
// 所有框架都使用相同 API，区别仅在于获取 $client 的方式
$client->serviceRegistry()->register('my-app', '10.0.0.1', 8080, [
    'id'    => 'my-app-1',
    'tags'  => ['v1'],
    'check' => ['ttl' => '30s'],
]);
```

**配置读取：**

```php
// 相同的 API，Laravel/Hyperf 自动走缓存
$dbHost = $client->configCenter()->get('app/db_host', 'default');
```

**热更新运行方式：**

| 框架 | 启动命令 / 方式 | 运行环境 |
|------|----------------|---------|
| Laravel | `php artisan consul:watch` | 独立 Artisan 进程 |
| Hyperf | `ConsulWatchProcess` (自动启动) | Swoole 协程 |
| webman | 在 `onWorkerStart` 中 fork | Worker 进程 |
| ThinkPHP | Timer::setInterval / Swoole Process | 独立进程 |

---

## 安装

```bash
# 核心包
composer require erikwang2013/consul-php

# PSR-18 实现（选其一）
composer require guzzlehttp/guzzle php-http/guzzle7-adapter php-http/discovery
```

### 框架集成包

```bash
# Laravel
composer require erikwang2013/consul-php-laravel

# Hyperf
composer require erikwang2013/consul-php-hyperf

# webman
composer require erikwang2013/consul-php-webman

# ThinkPHP
composer require erikwang2013/consul-php-thinkphp
```

---

## 快速开始（通用）

```php
use Erikwang2013\Consul\Client\ConsulClient;

// 基础用法
$client = new ConsulClient(['base_uri' => 'http://127.0.0.1:8500']);

// 带 ACL Token
$client = new ConsulClient([
    'base_uri' => 'http://127.0.0.1:8500',
    'token'    => 'your-consul-acl-token',
]);
```

Token 会自动通过 `X-Consul-Token` 请求头附加到所有请求中。

### 服务注册

支持 TTL、HTTP、TCP、gRPC 四种健康检查模式。

```php
$registry = $client->serviceRegistry();

// TTL 模式 — 应用主动发心跳
$registry->register('user-service', '192.168.1.10', 8080, [
    'id'    => 'user-service-1',
    'tags'  => ['v1', 'primary'],
    'meta'  => ['region' => 'cn-east'],
    'check' => [
        'ttl' => '30s',
        'deregister_critical_service_after' => '120s', // 心跳超时自动注销
    ],
]);

// HTTP 模式 — Consul 定期探测
$registry->register('web', '192.168.1.10', 80, [
    'id'    => 'web-1',
    'check' => [
        'http'     => 'http://192.168.1.10:80/health',
        'interval' => '10s',
        'timeout'  => '3s',
    ],
]);

// TCP 模式
$registry->register('mysql', '192.168.1.10', 3306, [
    'check' => ['tcp' => '192.168.1.10:3306', 'interval' => '10s'],
]);

// gRPC 模式
$registry->register('grpc-svc', '192.168.1.10', 50051, [
    'check' => ['grpc' => '192.168.1.10:50051', 'interval' => '10s'],
]);

// 心跳（TTL 模式）
$registry->heartbeat('user-service-1');

// 下线
$registry->deregister('user-service-1');
```

### 服务发现

内置 RoundRobin（默认）和 Random 两种负载均衡策略。

```php
$discovery = $client->serviceDiscovery();

// 全部健康实例
$instances = $discovery->healthyInstances('user-service');
// [
//   ['address' => '10.0.0.1', 'port' => 8080, 'service' => 'user-service', 'id' => 'user-1', 'tags' => ['v1']],
//   ['address' => '10.0.0.2', 'port' => 8080, 'service' => 'user-service', 'id' => 'user-2', 'tags' => ['v1']],
// ]

// 负载均衡选一个
$instance = $discovery->selectInstance('user-service');

// 自定义负载均衡策略
use Erikwang2013\Consul\Service\LoadBalancer\Random;
$discovery = new Discovery($health, loadBalancer: new Random());

// 监听服务实例变更
$discovery->watch('user-service', function (array $instances) {
    // 实例上下线时回调
});

// 停止监听（在另一进程/协程中调用）
$discovery->stop();
```

### 配置中心

```php
$config = $client->configCenter();

// 单个键
$dbHost = $config->get('app/db_host', 'localhost');

// 整个命名空间
$all = $config->namespace('app/');
// ['app/db_host' => 'mysql.local', 'app/redis_host' => 'redis.local', ...]

// 写入 / 删除
$config->set('app/cache_ttl', '3600');
$config->delete('app/old_key');

// 热更新
$watcher = $config->watch('app/');
$watcher
    ->setBlockingWait(30)   // 长轮询超时（秒）
    ->setPollInterval(10)   // 降级为轮询的间隔（秒）
    ->onChange(function (array $updated) {
        // 配置变更回调
    });
$watcher->start(); // 阻塞，放入独立进程/协程
// $watcher->stop();  // 在另一进程/协程中调用以停止监听
```

**热更新原理：** 优先 Consul blocking query（`index` 长轮询），网络异常时自动降级为定时轮询，连接恢复后自动切回长轮询。回调 + PSR-14 EventDispatcher 双通道通知。

**缓存策略：** 注入 PSR-16 缓存后，`get()` 和 `namespace()` 自动读写缓存。Watcher 始终读 Consul 实时数据，不走缓存。

### KV 存储

```php
$kv = $client->kv;

$kv->put('key', 'value');
$entry = $kv->get('key');              // null 表示不存在
$all = $kv->all('prefix/');            // 递归列出
$keys = $kv->keys('prefix/');          // 仅键名
$keys = $kv->keys('prefix/', '/');     // 按分隔符层级列出
$kv->delete('key');
```

### 健康检查 API

```php
$health = $client->health;

$health->service('user-service', ['passing' => true]);  // 仅健康实例
$health->node('node-1');                                 // 节点所有检查
$health->checks('user-service');                          // 服务所有检查
$health->state('critical');                               // 按状态：passing/warning/critical
```

### Session / 分布式锁

```php
$session = $client->session;

$sess = $session->create([
    'Name'     => 'lock-session',
    'TTL'      => '30s',
    'Behavior' => 'delete',    // 过期自动删除关联 KV
]);
$sessionId = $sess['ID'];

// 锁住资源
$locked = $client->kv->put('lock/resource', '1', ['acquire' => $sessionId]);

// 续约 / 释放
$session->renew($sessionId);
$session->destroy($sessionId);
```

### ACL

```php
$acl = $client->acl;

// Token
$token = $acl->tokenCreate(['Description' => 'read-only', 'Policies' => [['Name' => 'read-policy']]]);
$acl->tokenRead($token['AccessorID']);
$acl->tokenDelete($token['AccessorID']);

// Policy
$policy = $acl->policyCreate(['Name' => 'my-policy', 'Rules' => 'node "" { policy = "read" }']);

// Role
$role = $acl->roleCreate(['Name' => 'reader', 'Policies' => [['Name' => 'my-policy']]]);

// Login/Logout
$result = $acl->login(['AuthMethod' => 'my-auth', 'BearerToken' => '...']);
$acl->logout();
```

### 异步客户端

```php
use Erikwang2013\Consul\Client\ConsulAsyncClient;

$client = new ConsulAsyncClient(['base_uri' => 'http://127.0.0.1:8500']);

$promise = $client->wrap(fn() => $client->kv->get('key'));

$promise
    ->then(fn($result) => print_r($result))
    ->catch(fn(\Throwable $e) => log_error($e));

$value = $promise->wait(); // 阻塞获取结果
```

**注意：** 异步客户端基于 Promise 模式，适用于需要并发请求的场景。Hyperf 协程环境中默认的 HTTP 客户端即可实现协程级并发。

---

## 各框架集成指南

### Laravel

```bash
composer require erikwang2013/consul-php-laravel
php artisan vendor:publish --tag=consul-config
```

`.env` 中设置 `CONSUL_BASE_URI`，之后即可通过依赖注入或 Facade 使用。Laravel 扩展自动注入 PSR-18 客户端、PSR-16 缓存、PSR-3 日志和 PSR-14 事件分发器。

```php
// 依赖注入
use Erikwang2013\Consul\Client\ConsulClient;
public function show(ConsulClient $consul) { ... }

// Facade
use Consul;
$services = Consul::catalog->services();

// 配置热更新 — Artisan 命令
// php artisan consul:watch
```

### Hyperf

```bash
composer require erikwang2013/consul-php-hyperf
php bin/hyperf.php vendor:publish consul
```

Hyperf 扩展自动注册 `ConsulClient` 到 DI 容器，HTTP 请求默认使用 Swoole 协程客户端。服务注册建议放在 `MainServerStart` 事件监听中，热更新使用 `AbstractProcess` 在协程中运行。

```php
// 注解注入
#[Inject]
private ConsulClient $consul;

// 服务注册 — MainServerStart 事件
$consul->serviceRegistry()->register(...);

// 热更新 — ConsulWatchProcess 自动启动
```

### webman

```bash
composer require erikwang2013/consul-php-webman
```

webman 插件自动复制配置文件。由于 webman 是常驻内存架构，服务注册放在 `onWorkerStart` 回调中，全局只需注册一次。

```php
// process/ConsulRegister.php
class ConsulRegister {
    public function onWorkerStart(Worker $worker): void {
        $consul = new ConsulClient(['base_uri' => getenv('CONSUL_BASE_URI')]);
        $consul->serviceRegistry()->register('webman-app', ...);
    }
}
```

### ThinkPHP

```bash
composer require erikwang2013/consul-php-thinkphp
```

复制配置文件到 `config/consul.php`。通过 `bind` 将 `ConsulClient` 注册到容器，之后用 `app('consul')` 获取。

```php
// app/AppService.php
$this->app->bind('consul', fn() => new ConsulClient(config('consul')));

// 使用
$services = app('consul')->catalog->services();

// 助手函数 — app/common.php
function consul() { return app('consul'); }
```

---

## 自定义 HTTP 客户端

```php
$client = new ConsulClient(
    config:          ['base_uri' => 'http://consul:8500', 'token' => 'acl-token'],
    httpClient:      $myPsr18Client,        // 必填或自动发现
    requestFactory:  $myRequestFactory,      // 同上
    streamFactory:   $myStreamFactory,       // 同上
    logger:          $myLogger,              // PSR-3，可选
    cache:           $myCache,               // PSR-16，可选
    eventDispatcher: $myEventDispatcher,     // PSR-14，可选
);
```

---

## API 模块速查

| 属性 | 类 | 主要方法 |
|------|-----|---------|
| `$client->kv` | `Api\Kv` | `get` `put` `delete` `all` `keys` |
| `$client->agent` | `Api\Agent` | `members` `self` `registerService` `deregisterService` `checks` `services` |
| `$client->catalog` | `Api\Catalog` | `register` `deregister` `nodes` `services` `service` `node` |
| `$client->health` | `Api\Health` | `service` `node` `checks` `state` |
| `$client->session` | `Api\Session` | `create` `destroy` `renew` `info` `all` `node` |
| `$client->acl` | `Api\Acl` | `token*` `policy*` `role*` `authMethod*` `login` `logout` `bootstrap` |
| `$client->event` | `Api\Event` | `fire` `list` |
| `$client->status` | `Api\Status` | `leader` `peers` |
| `$client->coordinate` | `Api\Coordinate` | `datacenters` `nodes` `node` |
| `$client->operator` | `Api\Operator` | `raftConfig` `autopilotConfig` `keyring`（常量：`KEYRING_LIST` `KEYRING_INSTALL` `KEYRING_USE` `KEYRING_REMOVE`） |
| `$client->snapshot` | `Api\Snapshot` | `save`（返回原始快照字节，通过 `getRaw()`） `restore`（发送原始字节，通过 `putRaw()`） |

高层封装：

| 方法 | 返回 | 说明 |
|------|------|------|
| `$client->serviceRegistry()` | `Service\Registry` | 服务注册/心跳/下线 |
| `$client->serviceDiscovery()` | `Service\Discovery` | 实例列表/负载均衡/变更监听 |
| `$client->configCenter()` | `Config\ConfigCenter` | 配置读写/缓存/热更新 |

---

## 异常体系

所有异常继承 `ConsulException`（继承 `RuntimeException`）：

```
ConsulException
├── ClientException           HTTP 传输错误（连接失败、DNS、超时等）
├── ServerException           Consul 返回 5xx
└── ConsulRequestException    Consul 返回 4xx
    ├── NotFoundException     404
    └── AccessDeniedException 403
```

```php
try {
    $client->kv->get('key');
} catch (ClientException $e) {
    // 网络问题
} catch (NotFoundException $e) {
    // 资源不存在
} catch (ConsulException $e) {
    // 其他 Consul 错误
}
```

---

## 架构

```
┌─────────────────────────────────────┐
│            ConsulClient              │  ← 统一入口（同步 + 异步）
├─────────────────────────────────────┤
│  Service\Registry │ Config\Config   │  ← 高层封装
│  Service\Discovery│   Center        │
├─────────────────────────────────────┤
│  Api\Agent │ Api\Kv │ Api\Health   │  ← API 模块（11 个）
│  Api\Catalog │ Api\Session │ ...    │
├─────────────────────────────────────┤
│       Transport\Psr18Transport       │  ← PSR-18 传输层
│  get/put/post/delete + getRaw       │     Token 注入 · Header 捕获
│  putRaw + getWithHeaders             │     状态码检查 · JSON 解码
├─────────────────────────────────────┤
│   PSR-18 Client  │  PSR-17 Factory   │  ← 用户注入 / 自动发现
└─────────────────────────────────────┘
```

---

## 最低要求

- PHP 8.0+
- Composer
- PSR-18 HTTP Client 实现
- [可选] PSR-16 缓存 — `Discovery::healthyInstances()` / `ConfigCenter::get()` 自动缓存
- [可选] PSR-3 Logger — 请求日志
- [可选] PSR-14 EventDispatcher — `ConfigChangedEvent` 事件

## License

MIT
