# Merge Framework Extensions into Core

## Goal

Eliminate separate extension packages. Single `composer require erikwang2013/consul-php` auto-integrates with Laravel, Hyperf, webman, and ThinkPHP.

## Principle

PHP autoloading is lazy — class files load only when referenced. Framework-specific classes in `src/Integration/*` are safe: they won't trigger unless the target framework loads them via auto-discovery.

## Directory Structure

```
src/Integration/
  Laravel/
    ConsulServiceProvider.php    (namespace: Erikwang2013\Consul\Integration\Laravel)
    Facades/Consul.php
    config/consul.php
  Hyperf/
    ConfigProvider.php           (namespace: Erikwang2013\Consul\Integration\Hyperf)
    ConsulClientFactory.php
    config/consul.php
  Webman/
    Install.php                  (namespace: Erikwang2013\Consul\Integration\Webman)
    config/app.php
  Thinkphp/
    ConsulService.php            (namespace: Erikwang2013\Consul\Integration\Thinkphp)
    config/consul.php
```

## composer.json Changes

- Add `extra.laravel` for Laravel auto-discovery (providers + aliases)
- Add `extra.hyperf` for Hyperf auto-discovery (config provider)
- webman auto-discovers via `Install::WEBMAN_PLUGIN` constant — no composer.json `extra` needed
- ThinkPHP has no auto-discovery — documented manual registration
- Add `suggest` entries for each framework and guzzle
- Remove any reference to separate extension packages

## Files to Delete

- `extensions/` entire directory
- README references to separate extension packages

## Files to Update

- `composer.json` — merge extra + suggest from extensions
- `README.md` — update install docs, remove separate package references
- `phpunit.xml.dist` — update test paths if needed

## Non-Breaking

- Non-framework users: `new ConsulClient()` still works identically
- Core API (ConsulClient, KV, Health, etc.): zero changes
- Existing constructor signatures: unchanged
