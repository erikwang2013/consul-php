# erikwang2013/consul-php Design Spec

## Overview

PHP Consul client library targeting PHP 8.0+, providing full Consul HTTP API v1 coverage with emphasis on service registration/discovery and config center. Compatible with Laravel, Hyperf, webman, and ThinkPHP via framework-specific extension packages.

## Requirements Summary

- PHP 8.0+
- Full Consul HTTP API v1 coverage
- Service registration/discovery as primary use case
- Config center with hot-reload (blocking query + periodic polling)
- Sync and async API surfaces
- Core package with zero framework dependencies, plus per-framework extension packages
- PSR-18 HTTP Client abstraction (not coupled to a specific HTTP client)
- PSR-3 Logger, PSR-14 EventDispatcher, PSR-16 Cache support

## Package Structure

```
erikwang2013/consul-php/              # Core package (zero framework deps)
├── src/
│   ├── Client/                   # Sync + async client implementations
│   ├── Api/                      # Consul API modules (Agent, Catalog, Health, KV, etc.)
│   ├── Service/                  # High-level service register + discovery
│   ├── Config/                   # Config center with hot-reload
│   ├── Contract/                 # Interfaces
│   └── Exception/                # Exception hierarchy
├── tests/
└── composer.json

erikwang2013/consul-php-laravel/      # Laravel extension
erikwang2013/consul-php-hyperf/       # Hyperf extension (coroutine HTTP client)
erikwang2013/consul-php-webman/       # webman extension
erikwang2013/consul-php-thinkphp/     # ThinkPHP extension
```

## Dependencies (core package)

**Runtime:**
- **php** >=8.0.0
- **psr/http-client** ^1.0 — HTTP client abstraction
- **psr/http-message** ^1.0|^2.0 — Request/Response messages
- **psr/http-factory** ^1.0 — Request/Stream factories
- **psr/log** ^1.0|^2.0|^3.0 — Logger
- **psr/event-dispatcher** ^1.0 — Event dispatcher
- **psr/simple-cache** ^1.0|^2.0|^3.0 — Cache
- **psr/cache** ^1.0|^2.0|^3.0 — Cache pool
- **php-http/discovery** ^1.0 — PSR-18/PSR-17 auto-discovery

**Suggested (for auto-discovery):**
- `guzzlehttp/guzzle` — PSR-18 HTTP client
- `php-http/guzzle7-adapter` — PSR-18 adapter for Guzzle 7

Framework integration dependencies are in each extension package.

## Client Architecture

The client layer provides both sync and deferred-execution APIs sharing the same underlying transport:

```php
// Sync client — with optional ACL token
$client = new ConsulClient([
    'base_uri' => 'http://127.0.0.1:8500',
    'token'    => 'your-consul-acl-token',
]);
$services = $client->catalog->services();

// Deferred-execution client (Promise-based, not truly async I/O)
$client = new ConsulAsyncClient(['base_uri' => 'http://127.0.0.1:8500']);
$client->wrap(fn() => $client->kv->get('key'))
    ->then(fn($result) => handle($result))
    ->catch(fn($e) => log_error($e));
```

`ConsulAsyncClient` provides deferred/lazy execution via `Promise`, not true non-blocking I/O. For Swoole coroutine concurrency, use Hyperf's native coroutine HTTP client via the Hyperf extension.

The transport layer (`Psr18Transport`) adds `X-Consul-Token` header to every request when a token is configured. API modules access Consul via magic properties on `ConsulClient` (e.g., `$client->kv`, `$client->health`).

### TransportInterface

```
TransportInterface
├── get(path, query) → array           // JSON-decoded response body
├── put(path, body, query) → array     // JSON-encoded request, decoded response
├── post(path, body, query) → array    // JSON-encoded request, decoded response
├── delete(path, query) → array        // JSON-decoded response body
├── getRaw(path, query) → string       // Raw response body (for binary endpoints like snapshot save)
├── putRaw(path, body, query) → array  // Raw request body with octet-stream content type
└── getWithHeaders(path, query) → array // Returns ['headers' => [...], 'body' => array]
```

`getWithHeaders()` enables blocking queries by exposing `X-Consul-Index` and other Consul response headers. `getRaw()` and `putRaw()` handle binary endpoints (snapshot save/restore) that are incompatible with JSON encoding.

### Token Authentication

Token is passed via `$config['token']` to `ConsulClient`, forwarded to `Psr18Transport`, and attached as `X-Consul-Token` header on every HTTP request. This works transparently across all API modules.

## Consul API Modules

All v1 API endpoints organized into modules:

| Module | Coverage |
|--------|----------|
| Agent | members, self, maintenance, join, leave, reload |
| Catalog | register, deregister, nodes, services, service nodes |
| Health | node checks, service checks, state by check/service |
| KV | get, put, delete, list (recursive), raw |
| Session | create, destroy, renew, info, node sessions |
| ACL | tokens, roles, policies, legacy + new ACL |
| Event | fire, list |
| Status | leader, peers |
| Coordinate | datacenters, nodes |
| Operator | raft config/peers, autopilot, keyring (constants: KEYRING_LIST/INSTALL/USE/REMOVE) |
| Snapshot | snapshot save (binary via getRaw) / restore (binary via putRaw) |

## Service Registry & Discovery

### Registry

```php
$registry = $client->serviceRegistry();

$registry->register('user-service', '10.0.0.1', 8080, [
    'id'    => 'user-service-1',
    'check' => ['ttl' => '30s'],
    'tags'  => ['v1', 'primary'],
    'meta'  => ['region' => 'cn-east'],
]);

$registry->heartbeat('user-service-1');       // TTL pass
$registry->heartbeatFail('user-service-1');   // TTL fail
$registry->deregister('user-service-1');      // Graceful shutdown
```

Registers a service with the local Consul agent via `/agent/service/register`. Health check supports TTL, HTTP, TCP, and gRPC modes. Heartbeat is provided as a method on the registry for TTL-based checks; external-check modes are configured at registration time and validated by Consul.

### Discovery

```php
$discovery = $client->serviceDiscovery();

$instances = $discovery->healthyInstances('user-service');

// Select a single instance via load balancing
$instance = $discovery->selectInstance('user-service');

// Watch for changes (blocking — run in a separate process/coroutine)
$discovery->watch('user-service', function (array $instances) {
    // Triggered when instance list changes
});

// Stop watching (called from another process/coroutine)
$discovery->stop();

// Inject a logger for error visibility
$discovery = new Discovery($health, logger: $myLogger);
```

`healthyInstances` queries `/health/service/:name` with `passing` filter. Returns normalized instance arrays with `address`, `port`, `service`, `id`, `tags`, `meta` keys.

Load balancing strategies: round-robin (default), random, and a `LoadBalancerInterface` for custom strategies.

`watch()` uses `getWithHeaders()` to propagate `X-Consul-Index` across blocking query iterations. Errors are logged via PSR-3 (falls back to `NullLogger`).

## Config Center

```php
$config = $client->configCenter();

$value = $config->get('app/db_host', 'localhost');
$tree = $config->namespace('app/');

$watcher = $config->watch('app/');
$watcher->onChange(function ($updated) { ... });
$watcher->start();    // Start watching
$watcher->stop();     // Stop
```

### Hot-reload Watcher

- **Primary:** Consul KV blocking query with `X-Consul-Index` tracking via `getWithHeaders()`
- **Fallback:** Automatic degradation to periodic polling when blocking query errors. After 5 consecutive successful polls, automatically recovers back to blocking queries
- **Notification:** Callback + PSR-14 EventDispatcher (`ConfigChangedEvent`) dual-channel notification
- **Snapshot comparison:** `ksort`-based deterministic config snapshots prevent false-change firing
- **Logging:** PSR-3 logger support with `NullLogger` fallback; logs errors in both blocking and polling paths
- Configurable blocking wait and poll interval via `setBlockingWait()` / `setPollInterval()`
- `stop()` method for graceful shutdown from another process/coroutine

### Caching

`get()` and `namespace()` support an optional PSR-16 cache layer. Cache TTL is configurable. The watcher bypasses cache (always reads real-time from Consul).

## Exception Hierarchy

```
\RuntimeException
└── ConsulException (base)
    ├── ClientException           — HTTP transport errors (connection, DNS, timeout)
    ├── ServerException           — Consul 5xx errors
    └── ConsulRequestException    — Consul 4xx errors
        ├── NotFoundException     — 404
        └── AccessDeniedException — 403
```

## Framework Extension Packages

Each framework extension provides:
- Service provider / plugin registration
- Framework-native config publishing (`config/consul.php`)
- Injection of framework-appropriate HTTP client
- Framework logger/event/cache bridge with safe `bound()`/`has()` checks — gracefully degrades to `null` when optional services (Cache, EventDispatcher) are not registered
- Facade or helper function (where idiomatic for the framework)

### Laravel (`erikwang2013/consul-php-laravel`)
- ServiceProvider auto-registers `ConsulClient` into the container
- Facade `Consul` for static access
- Config published via `vendor:publish`
- Default HTTP: Guzzle via PSR-18

### Hyperf (`erikwang2013/consul-php-hyperf`)
- ConfigProvider with auto-discovery
- Inject Swoole coroutine HTTP client for async support
- Compatible with Hyperf's coroutine context and dependency injection

### webman (`erikwang2013/consul-php-webman`)
- Plugin-based install via webman plugin system
- Config file under `plugin/erikwang2013/consul-php/`
- Default HTTP: Guzzle via PSR-18

### ThinkPHP (`erikwang2013/consul-php-thinkphp`)
- Service class registered in container
- Config file under `config/consul.php`
- Default HTTP: Guzzle via PSR-18

## Testing Strategy

- PHPUnit 9+ (for PHP 8.0+)
- 103 unit tests, 137 assertions covering all API modules, transport, exceptions, load balancers, and service classes
- Token header injection regression test
- Snapshot save (binary) and restore (raw body) tested with putRaw/getRaw
- Framework extensions tested via mock container bindings
- PHPStan level 5 compliance across all source files

## CI / Tooling

- PHP 8.0–8.4 compatibility
- PHPStan level 5 (clean, zero errors)
- PHP CS Fixer (PSR-12)
- Composer validate (lock file freshness)
