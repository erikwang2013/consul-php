# Merge Framework Extensions into Core — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Move four framework extensions (Laravel, Hyperf, webman, ThinkPHP) from `extensions/` into core `src/Integration/` so one `composer require erikwang2013/consul-php` auto-integrates with any framework.

**Architecture:** Framework adapter classes live under `src/Integration/{Framework}/` with namespaces `Erikwang2013\Consul\Integration\{Framework}\`. Each framework's auto-discovery metadata is merged into the core `composer.json` `extra` key. PHP's lazy autoloading ensures framework-specific classes only load when the target framework triggers them — non-framework projects never load those files.

**Tech Stack:** PHP 8.0+, PSR-4 autoloading, framework auto-discovery mechanisms

---

### Task 1: Create Laravel integration files

**Files:**
- Create: `src/Integration/Laravel/ConsulServiceProvider.php`
- Create: `src/Integration/Laravel/Facades/Consul.php`
- Create: `src/Integration/Laravel/config/consul.php`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p src/Integration/Laravel/Facades
mkdir -p src/Integration/Laravel/config
```

- [ ] **Step 2: Write Laravel config**

Write `src/Integration/Laravel/config/consul.php`:

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

- [ ] **Step 3: Write ConsulServiceProvider**

Write `src/Integration/Laravel/ConsulServiceProvider.php`:

```php
<?php

namespace Erikwang2013\Consul\Integration\Laravel;

use Erikwang2013\Consul\Client\ConsulClient;
use Illuminate\Support\ServiceProvider;

class ConsulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/config/consul.php', 'consul');

        $this->app->singleton(ConsulClient::class, function ($app) {
            $config = $app['config']['consul'];

            return new ConsulClient(
                $config,
                $app->make(\Psr\Http\Client\ClientInterface::class),
                $app->make(\Psr\Http\Message\RequestFactoryInterface::class),
                $app->make(\Psr\Http\Message\StreamFactoryInterface::class),
                $app->make(\Psr\Log\LoggerInterface::class),
                ($config['cache']['enable'] ?? false) && $app->bound(\Psr\SimpleCache\CacheInterface::class)
                    ? $app->make(\Psr\SimpleCache\CacheInterface::class)
                    : null,
                $app->bound(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    ? $app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    : null
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config/consul.php' => config_path('consul.php'),
        ], 'consul-config');
    }
}
```

- [ ] **Step 4: Write Laravel Facade**

Write `src/Integration/Laravel/Facades/Consul.php`:

```php
<?php

namespace Erikwang2013\Consul\Integration\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Consul extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Erikwang2013\Consul\Client\ConsulClient::class;
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Integration/Laravel/
git commit -m "feat: add Laravel integration to core"
```

---

### Task 2: Create Hyperf integration files

**Files:**
- Create: `src/Integration/Hyperf/ConfigProvider.php`
- Create: `src/Integration/Hyperf/ConsulClientFactory.php`
- Create: `src/Integration/Hyperf/config/consul.php`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p src/Integration/Hyperf/config
```

- [ ] **Step 2: Write Hyperf config**

Write `src/Integration/Hyperf/config/consul.php`:

```php
<?php

declare(strict_types=1);

return [
    'base_uri' => (function_exists('env') ? env('CONSUL_BASE_URI', 'http://127.0.0.1:8500') : (getenv('CONSUL_BASE_URI') ?: 'http://127.0.0.1:8500')),
    'token'    => (function_exists('env') ? env('CONSUL_TOKEN', '') : (getenv('CONSUL_TOKEN') ?: '')),
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
];
```

- [ ] **Step 3: Write ConfigProvider**

Write `src/Integration/Hyperf/ConfigProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Hyperf;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Erikwang2013\Consul\Client\ConsulClient::class => ConsulClientFactory::class,
            ],
            'publish' => [
                [
                    'id'          => 'consul',
                    'description' => 'Consul config',
                    'source'      => __DIR__ . '/config/consul.php',
                    'destination' => BASE_PATH . '/config/autoload/consul.php',
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Write ConsulClientFactory**

Write `src/Integration/Hyperf/ConsulClientFactory.php`:

```php
<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Integration\Hyperf;

use Erikwang2013\Consul\Client\ConsulClient;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulClientFactory
{
    public function __invoke(ContainerInterface $container): ConsulClient
    {
        $config = $container->get(ConfigInterface::class)->get('consul', []);

        return new ConsulClient(
            $config,
            $container->get(\Psr\Http\Client\ClientInterface::class),
            $container->get(\Psr\Http\Message\RequestFactoryInterface::class),
            $container->get(\Psr\Http\Message\StreamFactoryInterface::class),
            $container->get(LoggerInterface::class),
            ($config['cache']['enable'] ?? false) && $container->has(CacheInterface::class)
                ? $container->get(CacheInterface::class)
                : null,
            $container->has(\Psr\EventDispatcher\EventDispatcherInterface::class)
                ? $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class)
                : null
        );
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add src/Integration/Hyperf/
git commit -m "feat: add Hyperf integration to core"
```

---

### Task 3: Create webman integration files

**Files:**
- Create: `src/Integration/Webman/Install.php`
- Create: `src/Integration/Webman/config/app.php`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p src/Integration/Webman/config
```

- [ ] **Step 2: Write webman config**

Write `src/Integration/Webman/config/app.php`:

```php
<?php

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

- [ ] **Step 3: Write Install.php**

Write `src/Integration/Webman/Install.php`:

```php
<?php

namespace Erikwang2013\Consul\Integration\Webman;

class Install
{
    public const WEBMAN_PLUGIN = true;

    public static function install(): void
    {
        $configDir = config_path() . '/plugin/erikwang2013/consul-php';
        if (!is_dir($configDir) && !mkdir($configDir, 0755, true) && !is_dir($configDir)) {
            throw new \RuntimeException("Failed to create config directory: {$configDir}");
        }

        $configPath = $configDir . '/app.php';
        if (!file_exists($configPath)) {
            $source = __DIR__ . '/config/app.php';
            if (!copy($source, $configPath)) {
                throw new \RuntimeException("Failed to copy config file to: {$configPath}");
            }
        }
    }

    public static function uninstall(): void
    {
        $configPath = config_path() . '/plugin/erikwang2013/consul-php/app.php';
        if (file_exists($configPath) && !unlink($configPath)) {
            throw new \RuntimeException("Failed to remove config file: {$configPath}");
        }
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Integration/Webman/
git commit -m "feat: add webman integration to core"
```

---

### Task 4: Create ThinkPHP integration files

**Files:**
- Create: `src/Integration/Thinkphp/ConsulService.php`
- Create: `src/Integration/Thinkphp/config/consul.php`

- [ ] **Step 1: Create directory structure**

```bash
mkdir -p src/Integration/Thinkphp/config
```

- [ ] **Step 2: Write ThinkPHP config**

Write `src/Integration/Thinkphp/config/consul.php`:

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

- [ ] **Step 3: Write ConsulService**

Write `src/Integration/Thinkphp/ConsulService.php`:

```php
<?php

namespace Erikwang2013\Consul\Integration\Thinkphp;

use Erikwang2013\Consul\Client\ConsulClient;
use think\Service;

class ConsulService extends Service
{
    public function register(): void
    {
        $this->app->bind('consul', function () {
            $config = $this->app->config->get('consul', []);

            return new ConsulClient(
                $config,
                $this->app->make(\Psr\Http\Client\ClientInterface::class),
                $this->app->make(\Psr\Http\Message\RequestFactoryInterface::class),
                $this->app->make(\Psr\Http\Message\StreamFactoryInterface::class),
                $this->app->make(\Psr\Log\LoggerInterface::class),
                isset($config['cache']['enable']) && $config['cache']['enable'] && $this->app->bound(\Psr\SimpleCache\CacheInterface::class)
                    ? $this->app->make(\Psr\SimpleCache\CacheInterface::class)
                    : null,
                $this->app->bound(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    ? $this->app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
                    : null
            );
        });
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add src/Integration/Thinkphp/
git commit -m "feat: add ThinkPHP integration to core"
```

---

### Task 5: Update composer.json

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Update composer.json**

Read current `composer.json`, then write replacement with merged `extra` and `suggest`:

```json
{
    "name": "erikwang2013/consul-php",
    "description": "PHP Consul client with full API v1 coverage, service discovery, and config center. Auto-integrates with Laravel, Hyperf, webman, and ThinkPHP.",
    "type": "library",
    "license": "MIT",
    "authors": [
        {"name": "erik", "email": "erik@erik.xyz"}
    ],
    "require": {
        "php": ">=8.0",
        "psr/http-client": "^1.0",
        "psr/http-message": "^1.0|^2.0",
        "psr/http-factory": "^1.0",
        "psr/log": "^1.0|^2.0|^3.0",
        "psr/event-dispatcher": "^1.0",
        "psr/simple-cache": "^1.0|^2.0|^3.0",
        "psr/cache": "^1.0|^2.0|^3.0",
        "php-http/discovery": "^1.0"
    },
    "suggest": {
        "guzzlehttp/guzzle": "PSR-18 HTTP client implementation",
        "php-http/guzzle7-adapter": "PSR-18 adapter for Guzzle 7",
        "laravel/framework": "For Laravel auto-integration (ServiceProvider + Facade)",
        "hyperf/framework": "For Hyperf auto-integration (ConfigProvider + coroutine client)",
        "workerman/webman-framework": "For webman auto-integration (plugin auto-install)",
        "topthink/framework": "For ThinkPHP integration (Service binding)"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "phpstan/phpstan": "^1.0",
        "friendsofphp/php-cs-fixer": "^3.0",
        "guzzlehttp/guzzle": "^7.0",
        "php-http/guzzle7-adapter": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang2013\\Consul\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Erikwang2013\\Consul\\Tests\\": "tests/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang2013\\Consul\\Integration\\Laravel\\ConsulServiceProvider"
            ],
            "aliases": {
                "Consul": "Erikwang2013\\Consul\\Integration\\Laravel\\Facades\\Consul"
            }
        },
        "hyperf": {
            "config": "Erikwang2013\\Consul\\Integration\\Hyperf\\ConfigProvider"
        }
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Regenerate autoloader**

```bash
composer dump-autoload
```

Expected: No errors.

- [ ] **Step 3: Commit**

```bash
git add composer.json
git commit -m "feat: merge framework auto-discovery into core composer.json"
```

---

### Task 6: Delete extensions/ directory

**Files:**
- Delete: `extensions/` (entire directory)

- [ ] **Step 1: Remove extensions directory**

```bash
git rm -rf extensions/
```

- [ ] **Step 2: Commit**

```bash
git commit -m "chore: remove standalone extension packages"
```

---

### Task 7: Update README.md

**Files:**
- Modify: `README.md`

- [ ] **Step 1: Update README**

Key changes to make in `README.md`:

1. Replace the "框架集成一览" table row showing extension package names from:
   `| **扩展包** | consul-php-laravel | consul-php-hyperf | consul-php-webman | consul-php-thinkphp |`
   to:
   `| **扩展包** | 内置 | 内置 | 内置 | 内置 |`

2. Replace the "框架集成包" install section (lines 89-100) with:

```markdown
### 框架集成

框架适配已内置在核心包中，无需额外安装。安装核心包后，对应框架会自动发现并注册 Consul 服务：

- **Laravel** — 自动发现 `ConsulServiceProvider`，提供 `Consul` Facade 和依赖注入
- **Hyperf** — 自动发现 `ConfigProvider`，提供协程客户端工厂和 `#[Inject]` 注入
- **webman** — 自动发现插件，`composer install` 时自动复制配置文件
- **ThinkPHP** — 在 `app/service` 目录下创建 `ConsulService` 并注册到应用
```

3. Update namespace references from `Erikwang2013\Consul\Laravel` to `Erikwang2013\Consul\Integration\Laravel` (same for Hyperf, Webman, Thinkphp).

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: update README for merged framework integrations"
```

---

### Task 8: Verify

- [ ] **Step 1: Run PHPStan**

```bash
vendor/bin/phpstan analyse src/ --level=5
```

Expected: No new errors compared to baseline. (Framework integration files reference non-installed framework classes, which will produce errors. These files are only loaded by their respective frameworks — add them to PHPStan's exclude list if needed.)

- [ ] **Step 2: Run PHPUnit**

```bash
vendor/bin/phpunit
```

Expected: All existing tests pass.

- [ ] **Step 3: Verify autoloading does not crash in non-framework environment**

```bash
composer dump-autoload --optimize
php -r "echo 'autoload OK';"
```

Expected: `autoload OK` — optimized autoloader classmap scanning parses file paths but does not execute code. No crash.

- [ ] **Step 4: Verify PSR-4 namespace resolution**

```bash
php -r '
$loader = require "vendor/autoload.php";
$file = $loader->findFile("Erikwang2013\\Consul\\Integration\\Laravel\\ConsulServiceProvider");
echo $file ? "resolved: $file" : "NOT FOUND";
'
```

Expected: Output shows the resolved file path under `src/Integration/Laravel/ConsulServiceProvider.php`.
```
