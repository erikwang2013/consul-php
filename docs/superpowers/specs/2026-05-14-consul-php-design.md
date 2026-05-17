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

- **psr/http-client** ^1.0 — HTTP client abstraction
- **psr/http-message** ^1.0|^2.0 — Request/Response messages
- **psr/log** ^1.0|^2.0|^3.0 — Logger
- **psr/event-dispatcher** ^1.0 — Event dispatcher
- **psr/simple-cache** ^1.0|^2.0|^3.0 — Cache
- **psr/cache** ^1.0|^2.0|^3.0 — Cache pool
- **php** >=8.0.0

No other runtime dependencies. Framework integration dependencies are in each extension package.

## Client Architecture

The client layer provides both sync and async APIs sharing the same underlying transport:

```php
// Sync client
$client = new ConsulClient(['base_uri' => 'http://127.0.0.1:8500']);
$services = $client->catalog->services();

// Async client
$client = new ConsulAsyncClient(['base_uri' => 'http://127.0.0.1:8500']);
$client->catalog->services()->then(function ($services) { ... });
```

`HttpClientInterface` abstracts the actual HTTP transport. By default the sync client binds a PSR-18 implementation, and the async client wraps it with a promise adapter. Framework extensions inject the appropriate transport (Swoole coroutine client for Hyperf, Guzzle async handler for Laravel/webman/ThinkPHP).

Each Consul API module is written once. The client base class handles the sync/async dispatch so modules don't duplicate logic.

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
| Operator | raft config/peers, autopilot, keyring |
| Snapshot | snapshot save/restore |

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

$discovery->watch('user-service', function ($instances) {
    // Triggered when instance list changes
});
```

`healthyInstances` queries `/health/service/:name` with `passing` filter. Returns a typed collection of service instances.

Load balancing strategies: round-robin (default), random, and an interface for custom strategies.

Service watch uses Consul blocking queries internally for near-real-time updates.

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

- Primary: Consul KV blocking query with index tracking
- Fallback: automatic degradation to periodic polling when blocking query errors or timeouts
- Emits changes via PSR-14 EventDispatcher, bridging to framework event systems
- Configurable poll interval for fallback mode

### Caching

`get()` and `namespace()` support an optional PSR-16 cache layer. Cache TTL is configurable. The watcher bypasses cache (always reads real-time from Consul).

## Exception Hierarchy

```
ConsulException (base, extends RuntimeException)
├── ClientException          — HTTP transport errors
├── ServerException          — Consul 5xx errors
├── ConsulRequestException   — Consul 4xx errors
│   ├── NotFoundException
│   └── AccessDeniedException
└── ConsulException          — Other Consul-level errors
```

## Framework Extension Packages

Each framework extension provides:
- Service provider / plugin registration
- Framework-native config publishing (`config/consul.php`)
- Injection of framework-appropriate HTTP client
- Framework logger/event/cache bridge
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
- Unit tests against HTTP mocks (PSR-18 mock client) for all API modules
- Integration tests against a real Consul agent (optional, CI-gated)
- Each framework extension has smoke tests ensuring container bindings resolve

## CI / Tooling

- GitHub Actions: PHP 8.0, 8.1, 8.2, 8.3, 8.4 matrix
- PHPStan level 6+
- PHP CS Fixer (PSR-12)
- Composer validate (lock file freshness)
