# erikwang/consul-php

PHP Consul 客户端，完整覆盖 Consul HTTP API v1，重点支持服务注册发现与配置中心。

PHP 8.0+ · PSR-18/PSR-3/PSR-14/PSR-16 · 零框架依赖

## 安装

```bash
composer require erikwang/consul-php
```

还需要安装 PSR-18 HTTP 客户端实现（推荐 Guzzle）：

```bash
composer require guzzlehttp/guzzle php-http/guzzle7-adapter php-http/discovery
```

## 快速开始

```php
use Erikwang\Consul\Client\ConsulClient;

$client = new ConsulClient(['base_uri' => 'http://127.0.0.1:8500']);
```

### 服务注册

```php
$registry = $client->serviceRegistry();

// TTL 健康检查 — 需要定期发送心跳
$registry->register('user-service', '192.168.1.10', 8080, [
    'id'    => 'user-service-1',
    'tags'  => ['v1', 'primary'],
    'meta'  => ['region' => 'cn-east'],
    'check' => ['ttl' => '30s'],
]);

// HTTP 健康检查 — Consul 主动探测
$registry->register('web', '192.168.1.10', 80, [
    'check' => ['http' => 'http://192.168.1.10:80/health', 'interval' => '10s'],
]);

// TCP 健康检查
$registry->register('mysql', '192.168.1.10', 3306, [
    'check' => ['tcp' => '192.168.1.10:3306', 'interval' => '10s'],
]);

// 心跳 — 仅 TTL 模式需要
$registry->heartbeat('user-service-1');

// 优雅下线
$registry->deregister('user-service-1');
```

### 服务发现

```php
$discovery = $client->serviceDiscovery();

// 获取健康实例
$instances = $discovery->healthyInstances('user-service');
foreach ($instances as $inst) {
    echo "{$inst['address']}:{$inst['port']}\n";
}

// 负载均衡选择单个实例
$instance = $discovery->selectInstance('user-service'); // null 表示无可用实例

// 监听服务变更
$discovery->watch('user-service', function (array $instances) {
    echo "实例列表已变更，当前 " . count($instances) . " 个\n";
});
```

### 配置中心

```php
$config = $client->configCenter();

// 读取单个配置
$dbHost = $config->get('app/db_host', 'localhost');

// 读取整个命名空间（前缀）
$appConfig = $config->namespace('app/');
// ['app/db_host' => 'mysql.local', 'app/port' => '3306', ...]

// 写入 / 删除
$config->set('app/cache_ttl', '3600');
$config->delete('app/old_key');

// 热更新监听
$watcher = $config->watch('app/');
$watcher->onChange(function (array $updated) {
    // 配置变更时自动回调
    print_r($updated);
});
$watcher->start(); // 阻塞运行，建议放入独立进程/协程
```

### KV 存储

```php
$kv = $client->kv;

$kv->put('my-key', 'my-value');
$entry = $kv->get('my-key');
echo base64_decode($entry['Value']); // 'my-value'

$allKeys = $kv->all('my-prefix/');  // 递归列出
$keys = $kv->keys('my-prefix/');    // 仅键名
$kv->delete('my-key');
```

### 健康检查

```php
$health = $client->health;

// 服务的健康实例（只返回 passing）
$passing = $health->service('user-service', ['passing' => true]);

// 节点健康
$nodeHealth = $health->node('node-1');

// 按状态查询
$critical = $health->state('critical');
```

### Session / 分布式锁

```php
$session = $client->session;

// 创建 session
$sess = $session->create(['Name' => 'lock-session', 'TTL' => '30s']);
$sessionId = $sess['ID'];

// 配合 KV 实现分布式锁
$client->kv->put('lock/resource', 'locked', ['acquire' => $sessionId]);

// 续约 / 销毁
$session->renew($sessionId);
$session->destroy($sessionId);
```

### ACL

```php
$acl = $client->acl;

$token = $acl->tokenCreate(['Description' => 'read-only', 'Policies' => [['Name' => 'read-policy']]]);
$tokens = $acl->tokenList();
$acl->tokenDelete($token['AccessorID']);

$policy = $acl->policyCreate(['Name' => 'my-policy', 'Rules' => 'node "" { policy = "read" }']);
```

### 异步客户端

```php
use Erikwang\Consul\Client\ConsulAsyncClient;

$client = new ConsulAsyncClient(['base_uri' => 'http://127.0.0.1:8500']);

// Promise 风格
$promise = $client->wrap(function () use ($client) {
    return $client->kv->get('config/key');
});

$promise->then(function ($result) {
    echo "Got: " . json_encode($result);
})->catch(function (\Throwable $e) {
    echo "Failed: " . $e->getMessage();
});

$value = $promise->wait(); // 阻塞等待结果
```

## API 模块速查

| 属性 | 类 | 说明 |
|------|-----|------|
| `$client->kv` | `Api\Kv` | 键值存储 |
| `$client->agent` | `Api\Agent` | Agent 管理 |
| `$client->catalog` | `Api\Catalog` | 服务/节点目录 |
| `$client->health` | `Api\Health` | 健康检查 |
| `$client->session` | `Api\Session` | Session/锁 |
| `$client->acl` | `Api\Acl` | 访问控制 |
| `$client->event` | `Api\Event` | 事件系统 |
| `$client->status` | `Api\Status` | 集群状态 |
| `$client->coordinate` | `Api\Coordinate` | 网络坐标 |
| `$client->operator` | `Api\Operator` | 运维操作 |
| `$client->snapshot` | `Api\Snapshot` | 快照备份 |

## 自定义 HTTP 客户端

```php
$client = new ConsulClient(
    config: ['base_uri' => 'http://consul:8500'],
    httpClient: $myPsr18Client,       // 必填或由 php-http/discovery 自动发现
    requestFactory: $myRequestFactory, // 同上
    streamFactory: $myStreamFactory,   // 同上
    logger: $myLogger,                 // PSR-3，可选
    cache: $myCache,                   // PSR-16，可选
    eventDispatcher: $myDispatcher,    // PSR-14，可选
);
```

## 配置中心热更新原理

Watcher 优先使用 Consul 原生 blocking query（长轮询，基于 KV 的 `index` 机制）。当 blocking query 出错或超时时自动降级为定时轮询。变更通过回调 + PSR-14 EventDispatcher 双通道通知。

```php
$watcher = $config->watch('app/');
$watcher
    ->setBlockingWait(30)  // blocking query 超时（秒）
    ->setPollInterval(10)   // 降级轮询间隔（秒）
    ->onChange(function (array $updated) {
        // 处理配置变更
    });
$watcher->start(); // 阻塞运行
```

## 框架集成

| 框架 | 扩展包 | 文档 |
|------|--------|------|
| Laravel | `erikwang/consul-php-laravel` | [extensions/laravel/README.md](extensions/laravel/README.md) |
| Hyperf | `erikwang/consul-php-hyperf` | [extensions/hyperf/README.md](extensions/hyperf/README.md) |
| webman | `erikwang/consul-php-webman` | [extensions/webman/README.md](extensions/webman/README.md) |
| ThinkPHP | `erikwang/consul-php-thinkphp` | [extensions/thinkphp/README.md](extensions/thinkphp/README.md) |

## 异常体系

```
ConsulException (RuntimeException)
├── ClientException          — HTTP 传输错误（连接失败等）
├── ServerException          — Consul 5xx 错误
└── ConsulRequestException   — Consul 4xx 错误
    ├── NotFoundException    — 404
    └── AccessDeniedException — 403
```

## 最低要求

- PHP 8.0+
- PSR-18 HTTP Client 实现（Guzzle 7、Symfony HttpClient 等）
- [可选] PSR-16 缓存实现 — 启用服务发现/配置缓存
- [可选] PSR-3 Logger — 请求日志
- [可选] PSR-14 EventDispatcher — 配置变更事件

## License

MIT
