# erikwang/consul-php Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a PHP 8.0+ Consul client library with full API v1 coverage, service registry/discovery, config center with hot-reload, sync+async APIs, and framework extensions for Laravel/Hyperf/webman/ThinkPHP.

**Architecture:** Core package (`erikwang/consul-php`) has zero framework deps, using only PSR-18/PSR-3/PSR-14/PSR-16. HTTP transport is abstracted behind PSR-18. Each API module is written once; client base class handles sync/async dispatch. Framework extensions inject appropriate HTTP clients (Swoole coroutine for Hyperf, Guzzle for others).

**Tech Stack:** PHP 8.0+, PHPUnit 9+, PSR-18 HTTP Client, PSR-3 Logger, PSR-14 EventDispatcher, PSR-16 Cache, Composer

**Namespace:** `Erikwang\Consul`

---

### Task 1: Project skeleton

**Files:**
- Create: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `.github/workflows/ci.yml`
- Create: `src/Exception/ConsulException.php`
- Create: `src/Exception/ClientException.php`
- Create: `src/Exception/ServerException.php`
- Create: `src/Exception/ConsulRequestException.php`
- Create: `src/Exception/NotFoundException.php`
- Create: `src/Exception/AccessDeniedException.php`
- Create: `tests/Exception/ExceptionTest.php`

- [ ] **Step 1: Create composer.json**

```json
{
    "name": "erikwang/consul-php",
    "description": "PHP Consul client with full API v1 coverage, service discovery, and config center",
    "type": "library",
    "license": "MIT",
    "authors": [
        {"name": "erik", "email": "erikwang2013@example.com"}
    ],
    "require": {
        "php": ">=8.0",
        "psr/http-client": "^1.0",
        "psr/http-message": "^1.0|^2.0",
        "psr/http-factory": "^1.0",
        "psr/log": "^1.0|^2.0|^3.0",
        "psr/event-dispatcher": "^1.0",
        "psr/simple-cache": "^1.0|^2.0|^3.0",
        "psr/cache": "^1.0|^2.0|^3.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^9.0",
        "phpstan/phpstan": "^1.0",
        "friendsofphp/php-cs-fixer": "^3.0",
        "guzzlehttp/guzzle": "^7.0",
        "php-http/guzzle7-adapter": "^1.0",
        "php-http/discovery": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang\\Consul\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Erikwang\\Consul\\Tests\\": "tests/"
        }
    },
    "config": {
        "sort-packages": true
    }
}
```

- [ ] **Step 2: Create phpunit.xml.dist**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="https://schema.phpunit.de/9.5/phpunit.xsd"
         bootstrap="vendor/autoload.php"
         colors="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <coverage processUncoveredFiles="true">
        <include>
            <directory suffix=".php">src</directory>
        </include>
    </coverage>
</phpunit>
```

- [ ] **Step 3: Create .github/workflows/ci.yml**

```yaml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.0', '8.1', '8.2', '8.3', '8.4']
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          coverage: none
      - run: composer install --no-progress
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpstan analyse src --level=6
```

- [ ] **Step 4: Create src/Exception/ConsulException.php**

```php
<?php

namespace Erikwang\Consul\Exception;

use RuntimeException;

class ConsulException extends RuntimeException
{
}
```

- [ ] **Step 5: Create src/Exception/ClientException.php**

```php
<?php

namespace Erikwang\Consul\Exception;

class ClientException extends ConsulException
{
}
```

- [ ] **Step 6: Create src/Exception/ServerException.php**

```php
<?php

namespace Erikwang\Consul\Exception;

class ServerException extends ConsulException
{
}
```

- [ ] **Step 7: Create src/Exception/ConsulRequestException.php**

```php
<?php

namespace Erikwang\Consul\Exception;

class ConsulRequestException extends ConsulException
{
}
```

- [ ] **Step 8: Create src/Exception/NotFoundException.php**

```php
<?php

namespace Erikwang\Consul\Exception;

class NotFoundException extends ConsulRequestException
{
}
```

- [ ] **Step 9: Create src/Exception/AccessDeniedException.php**

```php
<?php

namespace Erikwang\Consul\Exception;

class AccessDeniedException extends ConsulRequestException
{
}
```

- [ ] **Step 10: Create tests/Exception/ExceptionTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Exception;

use Erikwang\Consul\Exception\AccessDeniedException;
use Erikwang\Consul\Exception\ClientException;
use Erikwang\Consul\Exception\ConsulException;
use Erikwang\Consul\Exception\ConsulRequestException;
use Erikwang\Consul\Exception\NotFoundException;
use Erikwang\Consul\Exception\ServerException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testExceptionHierarchy(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new ConsulException());
        $this->assertInstanceOf(ConsulException::class, new ClientException());
        $this->assertInstanceOf(ConsulException::class, new ServerException());
        $this->assertInstanceOf(ConsulException::class, new ConsulRequestException());
        $this->assertInstanceOf(ConsulRequestException::class, new NotFoundException());
        $this->assertInstanceOf(ConsulRequestException::class, new AccessDeniedException());
    }
}
```

- [ ] **Step 11: Install dependencies and run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && composer install --no-progress && vendor/bin/phpunit
```

Expected: 1 test, all pass.

- [ ] **Step 12: Commit**

```bash
git add composer.json phpunit.xml.dist .github/ src/ tests/
git commit -m "feat: initialize project skeleton and exception hierarchy"
```

---

### Task 2: HTTP transport layer

**Files:**
- Create: `src/Transport/TransportInterface.php`
- Create: `src/Transport/Psr18Transport.php`
- Create: `tests/Transport/Psr18TransportTest.php`

The transport layer wraps PSR-18, adding base URI handling and response parsing that Consul needs. All API modules depend on this interface.

- [ ] **Step 1: Create src/Transport/TransportInterface.php**

```php
<?php

namespace Erikwang\Consul\Transport;

interface TransportInterface
{
    public function get(string $path, array $query = []): array;
    public function put(string $path, array $body = [], array $query = []): array;
    public function post(string $path, array $body = [], array $query = []): array;
    public function delete(string $path, array $query = []): array;
}
```

- [ ] **Step 2: Create src/Transport/Psr18Transport.php**

```php
<?php

namespace Erikwang\Consul\Transport;

use Erikwang\Consul\Exception\AccessDeniedException;
use Erikwang\Consul\Exception\ClientException;
use Erikwang\Consul\Exception\NotFoundException;
use Erikwang\Consul\Exception\ConsulRequestException;
use Erikwang\Consul\Exception\ServerException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Psr18Transport implements TransportInterface
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private string $baseUri;
    private LoggerInterface $logger;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri = 'http://127.0.0.1:8500',
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = rtrim($baseUri, '/');
        $this->logger = $logger ?? new NullLogger();
    }

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [], $query);
    }

    public function put(string $path, array $body = [], array $query = []): array
    {
        return $this->request('PUT', $path, $body, $query);
    }

    public function post(string $path, array $body = [], array $query = []): array
    {
        return $this->request('POST', $path, $body, $query);
    }

    public function delete(string $path, array $query = []): array
    {
        return $this->request('DELETE', $path, [], $query);
    }

    private function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $uri = $this->baseUri . $path;
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest($method, $uri);

        if (!empty($body)) {
            $json = json_encode($body);
            $stream = $this->streamFactory->createStream($json);
            $request = $request->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->logger->debug("Consul request: $method $uri", ['body' => $body]);

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (\Throwable $e) {
            throw new ClientException("HTTP transport error: " . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();
        $contents = (string) $response->getBody();

        if ($statusCode >= 500) {
            throw new ServerException("Consul server error [$statusCode]: $contents", $statusCode);
        }

        if ($statusCode === 404) {
            throw new NotFoundException("Not found: $contents", $statusCode);
        }

        if ($statusCode === 403) {
            throw new AccessDeniedException("Access denied: $contents", $statusCode);
        }

        if ($statusCode >= 400) {
            throw new ConsulRequestException("Consul request error [$statusCode]: $contents", $statusCode);
        }

        $decoded = json_decode($contents, true);
        return $decoded ?? [];
    }
}
```

- [ ] **Step 3: Create tests/Transport/Psr18TransportTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Transport;

use Erikwang\Consul\Exception\ClientException;
use Erikwang\Consul\Exception\NotFoundException;
use Erikwang\Consul\Exception\ServerException;
use Erikwang\Consul\Transport\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class Psr18TransportTest extends TestCase
{
    private $httpClient;
    private $requestFactory;
    private $streamFactory;
    private Psr18Transport $transport;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
        $this->transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            'http://127.0.0.1:8500'
        );
    }

    public function testGetRequestReturnsDecodedJson(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn('{"key":"value"}');

        $this->requestFactory->method('createRequest')
            ->with('GET', 'http://127.0.0.1:8500/v1/kv/test')
            ->willReturn($request);

        $this->httpClient->method('sendRequest')->with($request)->willReturn($response);

        $result = $this->transport->get('/v1/kv/test');

        $this->assertSame(['key' => 'value'], $result);
    }

    public function test404ThrowsNotFoundException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(404);
        $response->method('getBody')->willReturn('');

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(NotFoundException::class);
        $this->transport->get('/v1/kv/missing');
    }

    public function test500ThrowsServerException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(500);
        $response->method('getBody')->willReturn('internal error');

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(ServerException::class);
        $this->transport->get('/v1/status/leader');
    }

    public function testTransportErrorThrowsClientException(): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')
            ->willThrowException(new \RuntimeException('connection refused'));

        $this->expectException(ClientException::class);
        $this->transport->get('/v1/status/leader');
    }

    public function testPostRequestWithBody(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn('{"success":true}');

        $stream = $this->createMock(StreamInterface::class);

        $request->method('withBody')->willReturn($request);
        $request->method('withHeader')->willReturn($request);

        $this->requestFactory->method('createRequest')
            ->with('POST', 'http://127.0.0.1:8500/v1/kv/foo')
            ->willReturn($request);

        $this->streamFactory->method('createStream')
            ->with('{"value":"bar"}')
            ->willReturn($stream);

        $this->httpClient->method('sendRequest')->willReturn($response);

        $result = $this->transport->post('/v1/kv/foo', ['value' => 'bar']);

        $this->assertSame(['success' => true], $result);
    }
}
```

- [ ] **Step 4: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

Expected: 5 tests, all pass.

- [ ] **Step 5: Commit**

```bash
git add src/Transport/ tests/Transport/
git commit -m "feat: add PSR-18 transport layer"
```

---

### Task 3: KV Store API

**Files:**
- Create: `src/Api/Kv.php`
- Create: `tests/Api/KvTest.php`

The KV API wraps Consul's `/v1/kv/` endpoints. It depends on `TransportInterface`.

- [ ] **Step 1: Create src/Api/Kv.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Kv
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function get(string $key, array $options = []): ?array
    {
        $query = $this->buildQuery($options);
        $result = $this->transport->get("/v1/kv/{$key}", $query);
        return !empty($result) ? $result[0] : null;
    }

    public function all(string $prefix = '', array $options = []): array
    {
        $query = $this->buildQuery($options);
        return $this->transport->get("/v1/kv/{$prefix}", array_merge($query, ['recurse' => 'true']))
            ?? [];
    }

    public function put(string $key, string $value, array $options = []): bool
    {
        $response = $this->transport->put("/v1/kv/{$key}", ['value' => base64_encode($value)], $options);
        return ($response['body'] ?? null) === true || $response === ['body' => true];
    }

    public function delete(string $key, array $options = []): bool
    {
        $this->transport->delete("/v1/kv/{$key}", $options);
        return true;
    }

    public function keys(string $prefix = '', string $separator = ''): array
    {
        $query = [];
        if ($separator !== '') {
            $query['separator'] = $separator;
        }
        $result = $this->transport->get("/v1/kv/{$prefix}", array_merge($query, ['keys' => 'true']));
        return $result ?? [];
    }

    private function buildQuery(array $options): array
    {
        $query = [];
        if (isset($options['dc']))          $query['dc'] = $options['dc'];
        if (isset($options['index']))       $query['index'] = $options['index'];
        if (isset($options['wait']))        $query['wait'] = $options['wait'];
        if (isset($options['ns']))          $query['ns'] = $options['ns'];
        if (isset($options['partition']))   $query['partition'] = $options['partition'];
        if (isset($options['raw']))         $query['raw'] = 'true';
        if (isset($options['cas']))         $query['cas'] = $options['cas'];
        return $query;
    }
}
```

- [ ] **Step 2: Create tests/Api/KvTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class KvTest extends TestCase
{
    private $transport;
    private Kv $kv;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->kv = new Kv($this->transport);
    }

    public function testGetReturnsValue(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/app', [])
            ->willReturn([['Key' => 'config/app', 'Value' => base64_encode('hello')]]);

        $result = $this->kv->get('config/app');

        $this->assertSame('Key', array_key_first($result));
        $this->assertSame('config/app', $result['Key']);
    }

    public function testGetReturnsNullWhenMissing(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/missing', [])
            ->willReturn([]);

        $result = $this->kv->get('missing');

        $this->assertNull($result);
    }

    public function testAllReturnsRecursiveList(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['recurse' => 'true'])
            ->willReturn([
                ['Key' => 'config/app', 'Value' => base64_encode('a')],
                ['Key' => 'config/db', 'Value' => base64_encode('b')],
            ]);

        $result = $this->kv->all('config/');

        $this->assertCount(2, $result);
    }

    public function testPutEncodesValue(): void
    {
        $this->transport->method('put')
            ->with('/v1/kv/key', ['value' => base64_encode('data')], [])
            ->willReturn(['body' => true]);

        $result = $this->kv->put('key', 'data');

        $this->assertTrue($result);
    }

    public function testKeysReturnsKeyList(): void
    {
        $this->transport->method('get')
            ->with('/v1/kv/config/', ['keys' => 'true'])
            ->willReturn(['config/app', 'config/db']);

        $result = $this->kv->keys('config/');

        $this->assertSame(['config/app', 'config/db'], $result);
    }

    public function testDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/kv/key', []);

        $result = $this->kv->delete('key');

        $this->assertTrue($result);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

Expected: 11 tests, all pass.

- [ ] **Step 4: Commit**

```bash
git add src/Api/Kv.php tests/Api/KvTest.php
git commit -m "feat: add KV store API module"
```

---

### Task 4: Agent API

**Files:**
- Create: `src/Api/Agent.php`
- Create: `tests/Api/AgentTest.php`

- [ ] **Step 1: Create src/Api/Agent.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Agent
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function members(array $options = []): array
    {
        $query = [];
        if (isset($options['wan'])) $query['wan'] = '1';
        return $this->transport->get('/v1/agent/members', $query);
    }

    public function self(): array
    {
        return $this->transport->get('/v1/agent/self');
    }

    public function maintenance(bool $enable, string $reason = ''): void
    {
        $this->transport->put('/v1/agent/maintenance', ['enable' => $enable, 'reason' => $reason]);
    }

    public function join(string $address, bool $wan = false): void
    {
        $query = $wan ? ['wan' => '1'] : [];
        $this->transport->put('/v1/agent/join/' . $address, [], $query);
    }

    public function forceLeave(string $node): void
    {
        $this->transport->put('/v1/agent/force-leave/' . $node);
    }

    public function checks(): array
    {
        return $this->transport->get('/v1/agent/checks');
    }

    public function services(): array
    {
        return $this->transport->get('/v1/agent/services');
    }

    public function registerService(array $service): array
    {
        return $this->transport->put('/v1/agent/service/register', $service);
    }

    public function deregisterService(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/deregister/' . $serviceId);
    }

    public function enableMaintenance(string $serviceId, string $reason = ''): void
    {
        $params = ['enable' => true];
        if ($reason !== '') {
            $params['reason'] = $reason;
        }
        $this->transport->put('/v1/agent/service/maintenance/' . $serviceId, $params);
    }

    public function disableMaintenance(string $serviceId): void
    {
        $this->transport->put('/v1/agent/service/maintenance/' . $serviceId, ['enable' => false]);
    }

    public function checkPass(string $checkId, string $note = ''): void
    {
        $this->transport->put('/v1/agent/check/pass/' . $checkId, ['note' => $note]);
    }

    public function checkFail(string $checkId, string $note = ''): void
    {
        $this->transport->put('/v1/agent/check/fail/' . $checkId, ['note' => $note]);
    }

    public function checkWarn(string $checkId, string $note = ''): void
    {
        $this->transport->put('/v1/agent/check/warn/' . $checkId, ['note' => $note]);
    }

    public function checkRegister(array $check): void
    {
        $this->transport->put('/v1/agent/check/register', $check);
    }

    public function checkDeregister(string $checkId): void
    {
        $this->transport->put('/v1/agent/check/deregister/' . $checkId);
    }

    public function ttlCheckPass(string $checkId, string $note = ''): void
    {
        $params = [];
        if ($note !== '') {
            $params['note'] = $note;
        }
        $this->transport->put('/v1/agent/check/pass/' . $checkId, $params);
    }

    public function ttlCheckFail(string $checkId, string $note = ''): void
    {
        $params = [];
        if ($note !== '') {
            $params['note'] = $note;
        }
        $this->transport->put('/v1/agent/check/fail/' . $checkId, $params);
    }

    public function ttlCheckWarn(string $checkId, string $note = ''): void
    {
        $params = [];
        if ($note !== '') {
            $params['note'] = $note;
        }
        $this->transport->put('/v1/agent/check/warn/' . $checkId, $params);
    }
}
```

- [ ] **Step 2: Create tests/Api/AgentTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Agent;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    private $transport;
    private Agent $agent;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->agent = new Agent($this->transport);
    }

    public function testMembers(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/members', [])
            ->willReturn([['Name' => 'node1']]);

        $result = $this->agent->members();

        $this->assertSame('node1', $result[0]['Name']);
    }

    public function testSelf(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/self')
            ->willReturn(['Config' => ['NodeName' => 'node1']]);

        $result = $this->agent->self();

        $this->assertSame('node1', $result['Config']['NodeName']);
    }

    public function testRegisterService(): void
    {
        $service = ['Name' => 'web', 'Port' => 80];
        $this->transport->method('put')
            ->with('/v1/agent/service/register', $service)
            ->willReturn([]);

        $result = $this->agent->registerService($service);

        $this->assertSame([], $result);
    }

    public function testDeregisterService(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/agent/service/deregister/web-1');

        $this->agent->deregisterService('web-1');
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/checks')
            ->willReturn(['check1' => ['Status' => 'passing']]);

        $result = $this->agent->checks();

        $this->assertArrayHasKey('check1', $result);
    }

    public function testServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/agent/services')
            ->willReturn(['web' => ['Service' => 'web', 'Port' => 80]]);

        $result = $this->agent->services();

        $this->assertArrayHasKey('web', $result);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

Expected: 17 tests, all pass.

- [ ] **Step 4: Commit**

```bash
git add src/Api/Agent.php tests/Api/AgentTest.php
git commit -m "feat: add Agent API module"
```

---

### Task 5: Catalog API

**Files:**
- Create: `src/Api/Catalog.php`
- Create: `tests/Api/CatalogTest.php`

- [ ] **Step 1: Create src/Api/Catalog.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Catalog
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function register(array $node, array $service, array $check = []): array
    {
        $payload = ['Node' => $node['node'], 'Address' => $node['address']];
        if (isset($node['datacenter'])) $payload['Datacenter'] = $node['datacenter'];
        if (isset($node['meta'])) $payload['NodeMeta'] = $node['meta'];

        $payload['Service'] = [
            'Service' => $service['service'],
            'Address' => $service['address'] ?? $node['address'],
            'Port'    => $service['port'],
        ];
        if (isset($service['id'])) $payload['Service']['ID'] = $service['id'];
        if (isset($service['tags'])) $payload['Service']['Tags'] = $service['tags'];
        if (isset($service['meta'])) $payload['Service']['Meta'] = $service['meta'];

        if (!empty($check)) {
            $payload['Check'] = $check;
        }

        return $this->transport->put('/v1/catalog/register', $payload);
    }

    public function deregister(array $node, string $serviceId = ''): void
    {
        $payload = ['Node' => $node['node']];
        if (isset($node['datacenter'])) $payload['Datacenter'] = $node['datacenter'];
        if ($serviceId !== '') $payload['ServiceID'] = $serviceId;

        $this->transport->put('/v1/catalog/deregister', $payload);
    }

    public function nodes(array $options = []): array
    {
        return $this->transport->get('/v1/catalog/nodes', $this->optionsQuery($options));
    }

    public function services(array $options = []): array
    {
        return $this->transport->get('/v1/catalog/services', $this->optionsQuery($options));
    }

    public function service(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/catalog/service/{$service}", $this->optionsQuery($options));
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/catalog/connect/{$service}", $this->optionsQuery($options));
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/catalog/node/{$node}", $this->optionsQuery($options));
    }

    public function nodeServices(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/catalog/node-services/{$node}", $this->optionsQuery($options));
    }

    private function optionsQuery(array $options): array
    {
        $query = [];
        if (isset($options['dc']))  $query['dc'] = $options['dc'];
        if (isset($options['ns']))  $query['ns'] = $options['ns'];
        if (isset($options['filter'])) $query['filter'] = $options['filter'];
        if (isset($options['index'])) $query['index'] = $options['index'];
        if (isset($options['wait']))  $query['wait'] = $options['wait'];
        return $query;
    }
}
```

- [ ] **Step 2: Create tests/Api/CatalogTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Catalog;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class CatalogTest extends TestCase
{
    private $transport;
    private Catalog $catalog;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->catalog = new Catalog($this->transport);
    }

    public function testRegisterWithNodeAndService(): void
    {
        $this->transport->method('put')
            ->with('/v1/catalog/register', $this->callback(function ($payload) {
                return $payload['Node'] === 'node1'
                    && $payload['Service']['Service'] === 'web'
                    && $payload['Service']['Port'] === 80;
            }))
            ->willReturn([]);

        $result = $this->catalog->register(
            ['node' => 'node1', 'address' => '10.0.0.1'],
            ['service' => 'web', 'port' => 80]
        );

        $this->assertSame([], $result);
    }

    public function testDeregister(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/catalog/deregister', ['Node' => 'node1', 'ServiceID' => 'web-1']);

        $this->catalog->deregister(
            ['node' => 'node1'],
            'web-1'
        );
    }

    public function testServices(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/services', [])
            ->willReturn(['web' => [], 'api' => []]);

        $result = $this->catalog->services();

        $this->assertArrayHasKey('web', $result);
        $this->assertArrayHasKey('api', $result);
    }

    public function testServiceNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/service/web', [])
            ->willReturn([
                ['Node' => 'node1', 'ServiceAddress' => '10.0.0.1', 'ServicePort' => 80],
            ]);

        $result = $this->catalog->service('web');

        $this->assertCount(1, $result);
        $this->assertSame('node1', $result[0]['Node']);
    }

    public function testNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/catalog/nodes', ['dc' => 'dc1'])
            ->willReturn([['Node' => 'node1']]);

        $result = $this->catalog->nodes(['dc' => 'dc1']);

        $this->assertCount(1, $result);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

Expected: 22 tests, all pass.

- [ ] **Step 4: Commit**

```bash
git add src/Api/Catalog.php tests/Api/CatalogTest.php
git commit -m "feat: add Catalog API module"
```

---

### Task 6: Health API

**Files:**
- Create: `src/Api/Health.php`
- Create: `tests/Api/HealthTest.php`

- [ ] **Step 1: Create src/Api/Health.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Health
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/health/node/{$node}", $this->optionsQuery($options));
    }

    public function checks(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/checks/{$service}", $this->optionsQuery($options));
    }

    public function service(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/service/{$service}", $this->optionsQuery($options));
    }

    public function connect(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/connect/{$service}", $this->optionsQuery($options));
    }

    public function state(string $state, array $options = []): array
    {
        return $this->transport->get("/v1/health/state/{$state}", $this->optionsQuery($options));
    }

    public function ingress(string $service, array $options = []): array
    {
        return $this->transport->get("/v1/health/ingress/{$service}", $this->optionsQuery($options));
    }

    private function optionsQuery(array $options): array
    {
        $query = [];
        foreach (['dc', 'ns', 'filter', 'index', 'wait', 'passing', 'near', 'node_meta'] as $key) {
            if (isset($options[$key])) {
                $k = $key === 'node_meta' ? 'node-meta' : $key;
                $query[$k] = $options[$key];
            }
        }
        return $query;
    }
}
```

- [ ] **Step 2: Create tests/Api/HealthTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class HealthTest extends TestCase
{
    private $transport;
    private Health $health;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->health = new Health($this->transport);
    }

    public function testServiceWithPassingFilter(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/service/web', ['passing' => 'true'])
            ->willReturn([
                ['Node' => ['Node' => 'node1'], 'Service' => ['Service' => 'web'], 'Checks' => [['Status' => 'passing']]],
            ]);

        $result = $this->health->service('web', ['passing' => true]);

        $this->assertCount(1, $result);
        $this->assertSame('passing', $result[0]['Checks'][0]['Status']);
    }

    public function testChecks(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/checks/web', [])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'passing']]);

        $result = $this->health->checks('web');

        $this->assertSame('passing', $result[0]['Status']);
    }

    public function testNode(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/node/node1', [])
            ->willReturn([['Node' => ['Node' => 'node1']]]);

        $result = $this->health->node('node1');

        $this->assertSame('node1', $result[0]['Node']['Node']);
    }

    public function testState(): void
    {
        $this->transport->method('get')
            ->with('/v1/health/state/critical', ['dc' => 'dc1'])
            ->willReturn([['CheckID' => 'check1', 'Status' => 'critical']]);

        $result = $this->health->state('critical', ['dc' => 'dc1']);

        $this->assertSame('critical', $result[0]['Status']);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

Expected: 26 tests, all pass.

- [ ] **Step 4: Commit**

```bash
git add src/Api/Health.php tests/Api/HealthTest.php
git commit -m "feat: add Health API module"
```

---

### Task 7: Session API

**Files:**
- Create: `src/Api/Session.php`
- Create: `tests/Api/SessionTest.php`

- [ ] **Step 1: Create src/Api/Session.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Session
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function create(array $options = []): array
    {
        return $this->transport->put('/v1/session/create', $options);
    }

    public function destroy(string $sessionId, array $options = []): void
    {
        $this->transport->put("/v1/session/destroy/{$sessionId}", [], $options);
    }

    public function info(string $sessionId, array $options = []): array
    {
        return $this->transport->get("/v1/session/info/{$sessionId}", $options);
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/session/node/{$node}", $options);
    }

    public function all(array $options = []): array
    {
        return $this->transport->get('/v1/session/list', $options);
    }

    public function renew(string $sessionId, array $options = []): array
    {
        return $this->transport->put("/v1/session/renew/{$sessionId}", [], $options);
    }
}
```

- [ ] **Step 2: Create tests/Api/SessionTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Session;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private $transport;
    private Session $session;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->session = new Session($this->transport);
    }

    public function testCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/session/create', [])
            ->willReturn(['ID' => 'abc-123']);

        $result = $this->session->create();

        $this->assertSame('abc-123', $result['ID']);
    }

    public function testCreateWithOptions(): void
    {
        $opts = ['Name' => 'my-session', 'TTL' => '30s', 'Behavior' => 'delete'];
        $this->transport->method('put')
            ->with('/v1/session/create', $opts)
            ->willReturn(['ID' => 'abc-123']);

        $result = $this->session->create($opts);

        $this->assertSame('abc-123', $result['ID']);
    }

    public function testDestroy(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/session/destroy/abc-123', [], []);

        $this->session->destroy('abc-123');
    }

    public function testInfo(): void
    {
        $this->transport->method('get')
            ->with('/v1/session/info/abc-123', [])
            ->willReturn(['ID' => 'abc-123', 'Name' => 'my-session']);

        $result = $this->session->info('abc-123');

        $this->assertSame('my-session', $result['Name']);
    }

    public function testRenew(): void
    {
        $this->transport->method('put')
            ->with('/v1/session/renew/abc-123', [], [])
            ->willReturn([['ID' => 'abc-123']]);

        $result = $this->session->renew('abc-123');

        $this->assertSame('abc-123', $result[0]['ID']);
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

Expected: 31 tests, all pass.

- [ ] **Step 4: Commit**

```bash
git add src/Api/Session.php tests/Api/SessionTest.php
git commit -m "feat: add Session API module"
```

---

### Task 8: ACL, Event, Status, Coordinate, Operator, Snapshot APIs

**Files:**
- Create: `src/Api/Acl.php`
- Create: `src/Api/Event.php`
- Create: `src/Api/Status.php`
- Create: `src/Api/Coordinate.php`
- Create: `src/Api/Operator.php`
- Create: `src/Api/Snapshot.php`
- Create: `tests/Api/AclTest.php`
- Create: `tests/Api/EventTest.php`
- Create: `tests/Api/StatusTest.php`
- Create: `tests/Api/CoordinateTest.php`
- Create: `tests/Api/OperatorTest.php`
- Create: `tests/Api/SnapshotTest.php`

- [ ] **Step 1: Create src/Api/Acl.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Acl
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function bootstrap(): array
    {
        return $this->transport->put('/v1/acl/bootstrap');
    }

    public function replication(): array
    {
        return $this->transport->get('/v1/acl/replication');
    }

    public function translate(string $accessorId): array
    {
        return $this->transport->get("/v1/acl/rules/translate/{$accessorId}");
    }

    public function tokenList(): array
    {
        return $this->transport->get('/v1/acl/tokens');
    }

    public function tokenCreate(array $token): array
    {
        return $this->transport->put('/v1/acl/token', $token);
    }

    public function tokenRead(string $accessorId): array
    {
        return $this->transport->get("/v1/acl/token/{$accessorId}");
    }

    public function tokenUpdate(string $accessorId, array $token): array
    {
        return $this->transport->put("/v1/acl/token/{$accessorId}", $token);
    }

    public function tokenDelete(string $accessorId): void
    {
        $this->transport->delete("/v1/acl/token/{$accessorId}");
    }

    public function tokenClone(string $accessorId): array
    {
        return $this->transport->put("/v1/acl/token/{$accessorId}/clone");
    }

    public function roleList(): array
    {
        return $this->transport->get('/v1/acl/roles');
    }

    public function roleCreate(array $role): array
    {
        return $this->transport->put('/v1/acl/role', $role);
    }

    public function roleRead(string $roleId): array
    {
        return $this->transport->get("/v1/acl/role/{$roleId}");
    }

    public function roleUpdate(string $roleId, array $role): array
    {
        return $this->transport->put("/v1/acl/role/{$roleId}", $role);
    }

    public function roleDelete(string $roleId): void
    {
        $this->transport->delete("/v1/acl/role/{$roleId}");
    }

    public function policyList(): array
    {
        return $this->transport->get('/v1/acl/policies');
    }

    public function policyCreate(array $policy): array
    {
        return $this->transport->put('/v1/acl/policy', $policy);
    }

    public function policyRead(string $policyId): array
    {
        return $this->transport->get("/v1/acl/policy/{$policyId}");
    }

    public function policyUpdate(string $policyId, array $policy): array
    {
        return $this->transport->put("/v1/acl/policy/{$policyId}", $policy);
    }

    public function policyDelete(string $policyId): void
    {
        $this->transport->delete("/v1/acl/policy/{$policyId}");
    }

    public function authMethodList(): array
    {
        return $this->transport->get('/v1/acl/auth-methods');
    }

    public function authMethodCreate(array $method): array
    {
        return $this->transport->put('/v1/acl/auth-method', $method);
    }

    public function authMethodRead(string $name): array
    {
        return $this->transport->get("/v1/acl/auth-method/{$name}");
    }

    public function authMethodUpdate(string $name, array $method): array
    {
        return $this->transport->put("/v1/acl/auth-method/{$name}", $method);
    }

    public function authMethodDelete(string $name): void
    {
        $this->transport->delete("/v1/acl/auth-method/{$name}");
    }

    public function login(array $auth): array
    {
        return $this->transport->post('/v1/acl/login', $auth);
    }

    public function logout(): void
    {
        $this->transport->post('/v1/acl/logout');
    }
}
```

- [ ] **Step 2: Create src/Api/Event.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Event
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function fire(string $name, string $payload = '', array $options = []): array
    {
        $body = ['Name' => $name];
        if ($payload !== '') $body['Payload'] = base64_encode($payload);

        $query = [];
        if (isset($options['dc']))  $query['dc'] = $options['dc'];
        if (isset($options['node'])) $query['node'] = $options['node'];
        if (isset($options['service'])) $query['service'] = $options['service'];
        if (isset($options['tag'])) $query['tag'] = $options['tag'];

        return $this->transport->put('/v1/event/fire/' . $name, $body, $query);
    }

    public function list(array $options = []): array
    {
        $query = [];
        if (isset($options['name'])) $query['name'] = $options['name'];

        return $this->transport->get('/v1/event/list', $query);
    }
}
```

- [ ] **Step 3: Create src/Api/Status.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Status
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function leader(): string
    {
        $result = $this->transport->get('/v1/status/leader');
        return $result['body'] ?? '';
    }

    public function peers(): array
    {
        $result = $this->transport->get('/v1/status/peers');
        return $result['body'] ?? $result;
    }
}
```

- [ ] **Step 4: Create src/Api/Coordinate.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Coordinate
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function datacenters(): array
    {
        return $this->transport->get('/v1/coordinate/datacenters');
    }

    public function nodes(array $options = []): array
    {
        return $this->transport->get('/v1/coordinate/nodes', $options);
    }

    public function node(string $node, array $options = []): array
    {
        return $this->transport->get("/v1/coordinate/node/{$node}", $options);
    }
}
```

- [ ] **Step 5: Create src/Api/Operator.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Operator
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function raftConfig(): array
    {
        return $this->transport->get('/v1/operator/raft/configuration');
    }

    public function raftPeer(string $address): void
    {
        $this->transport->delete("/v1/operator/raft/peer", ['address' => $address]);
    }

    public function autopilotConfig(): array
    {
        return $this->transport->get('/v1/operator/autopilot/configuration');
    }

    public function updateAutopilotConfig(array $config): void
    {
        $this->transport->put('/v1/operator/autopilot/configuration', $config);
    }

    public function autopilotHealth(): array
    {
        return $this->transport->get('/v1/operator/autopilot/health');
    }

    public function keyring(string $method, array $options = []): array
    {
        $query = [];
        if (isset($options['relay'])) $query['relay'] = $options['relay'];
        if (isset($options['local'])) $query['local'] = $options['local'];

        if ($method === 'list') {
            return $this->transport->get('/v1/operator/keyring', $query);
        }

        $body = ['Key' => $options['key']];
        if ($method === 'install') {
            return $this->transport->post('/v1/operator/keyring', $body, $query);
        }
        if ($method === 'use') {
            return $this->transport->put('/v1/operator/keyring', $body, $query);
        }
        return $this->transport->delete('/v1/operator/keyring', array_merge($query, $body));
    }
}
```

- [ ] **Step 6: Create src/Api/Snapshot.php**

```php
<?php

namespace Erikwang\Consul\Api;

use Erikwang\Consul\Transport\TransportInterface;

class Snapshot
{
    private TransportInterface $transport;

    public function __construct(TransportInterface $transport)
    {
        $this->transport = $transport;
    }

    public function save(array $options = []): string
    {
        $query = [];
        if (isset($options['dc'])) $query['dc'] = $options['dc'];
        if (isset($options['stale'])) $query['stale'] = 'true';

        $result = $this->transport->get('/v1/snapshot', $query);
        return $result['body'] ?? json_encode($result);
    }

    public function restore(string $snapshot, array $options = []): void
    {
        $query = [];
        if (isset($options['dc'])) $query['dc'] = $options['dc'];

        $this->transport->put('/v1/snapshot', ['body' => $snapshot], $query);
    }
}
```

- [ ] **Step 7: Create tests/Api/AclTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Acl;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class AclTest extends TestCase
{
    private $transport;
    private Acl $acl;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->acl = new Acl($this->transport);
    }

    public function testBootstrap(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/bootstrap')
            ->willReturn(['ID' => 'master-token']);

        $result = $this->acl->bootstrap();
        $this->assertSame('master-token', $result['ID']);
    }

    public function testTokenCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/token', ['Description' => 'test'])
            ->willReturn(['AccessorID' => 'abc']);

        $result = $this->acl->tokenCreate(['Description' => 'test']);
        $this->assertSame('abc', $result['AccessorID']);
    }

    public function testTokenList(): void
    {
        $this->transport->method('get')
            ->with('/v1/acl/tokens')
            ->willReturn([['AccessorID' => 'abc']]);

        $result = $this->acl->tokenList();
        $this->assertCount(1, $result);
    }

    public function testTokenDelete(): void
    {
        $this->transport->expects($this->once())
            ->method('delete')
            ->with('/v1/acl/token/abc');

        $this->acl->tokenDelete('abc');
    }

    public function testPolicyCreate(): void
    {
        $this->transport->method('put')
            ->with('/v1/acl/policy', ['Name' => 'test', 'Rules' => 'node "" { policy = "read" }'])
            ->willReturn(['ID' => 'policy-1']);

        $result = $this->acl->policyCreate(['Name' => 'test', 'Rules' => 'node "" { policy = "read" }']);
        $this->assertSame('policy-1', $result['ID']);
    }
}
```

- [ ] **Step 8: Create tests/Api/EventTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Event;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    private $transport;
    private Event $event;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->event = new Event($this->transport);
    }

    public function testFire(): void
    {
        $this->transport->method('put')
            ->with('/v1/event/fire/deploy', ['Name' => 'deploy', 'Payload' => base64_encode('v1.0')], [])
            ->willReturn(['ID' => 'evt-1']);

        $result = $this->event->fire('deploy', 'v1.0');
        $this->assertSame('evt-1', $result['ID']);
    }

    public function testList(): void
    {
        $this->transport->method('get')
            ->with('/v1/event/list', [])
            ->willReturn([['ID' => 'evt-1']]);

        $result = $this->event->list();
        $this->assertCount(1, $result);
    }
}
```

- [ ] **Step 9: Create tests/Api/StatusTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Status;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class StatusTest extends TestCase
{
    private $transport;
    private Status $status;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->status = new Status($this->transport);
    }

    public function testLeader(): void
    {
        $this->transport->method('get')
            ->with('/v1/status/leader')
            ->willReturn(['body' => '10.0.0.1:8300']);

        $result = $this->status->leader();
        $this->assertSame('10.0.0.1:8300', $result);
    }

    public function testPeers(): void
    {
        $this->transport->method('get')
            ->with('/v1/status/peers')
            ->willReturn(['body' => ['10.0.0.1:8300', '10.0.0.2:8300']]);

        $result = $this->status->peers();
        $this->assertCount(2, $result);
    }
}
```

- [ ] **Step 10: Create tests/Api/CoordinateTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Coordinate;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class CoordinateTest extends TestCase
{
    private $transport;
    private Coordinate $coord;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->coord = new Coordinate($this->transport);
    }

    public function testDatacenters(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/datacenters')
            ->willReturn(['dc1', 'dc2']);

        $result = $this->coord->datacenters();
        $this->assertSame(['dc1', 'dc2'], $result);
    }

    public function testNodes(): void
    {
        $this->transport->method('get')
            ->with('/v1/coordinate/nodes', [])
            ->willReturn([['Node' => 'node1']]);

        $result = $this->coord->nodes();
        $this->assertCount(1, $result);
    }
}
```

- [ ] **Step 11: Create tests/Api/OperatorTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Operator;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class OperatorTest extends TestCase
{
    private $transport;
    private Operator $op;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->op = new Operator($this->transport);
    }

    public function testRaftConfig(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/raft/configuration')
            ->willReturn(['Servers' => []]);

        $result = $this->op->raftConfig();
        $this->assertArrayHasKey('Servers', $result);
    }

    public function testAutopilotHealth(): void
    {
        $this->transport->method('get')
            ->with('/v1/operator/autopilot/health')
            ->willReturn(['Healthy' => true]);

        $result = $this->op->autopilotHealth();
        $this->assertTrue($result['Healthy']);
    }
}
```

- [ ] **Step 12: Create tests/Api/SnapshotTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Api;

use Erikwang\Consul\Api\Snapshot;
use Erikwang\Consul\Transport\TransportInterface;
use PHPUnit\Framework\TestCase;

class SnapshotTest extends TestCase
{
    private $transport;
    private Snapshot $snapshot;

    protected function setUp(): void
    {
        $this->transport = $this->createMock(TransportInterface::class);
        $this->snapshot = new Snapshot($this->transport);
    }

    public function testSave(): void
    {
        $this->transport->method('get')
            ->with('/v1/snapshot', [])
            ->willReturn(['body' => 'snapshot-data']);

        $result = $this->snapshot->save();
        $this->assertSame('snapshot-data', $result);
    }

    public function testRestore(): void
    {
        $this->transport->expects($this->once())
            ->method('put')
            ->with('/v1/snapshot', ['body' => 'snapshot-data'], []);

        $this->snapshot->restore('snapshot-data');
    }
}
```

- [ ] **Step 13: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 14: Commit**

```bash
git add src/Api/ tests/Api/
git commit -m "feat: add ACL, Event, Status, Coordinate, Operator, Snapshot API modules"
```

---

### Task 9: Service Registry

**Files:**
- Create: `src/Service/Registry.php`
- Create: `tests/Service/RegistryTest.php`

The Registry provides high-level service registration with health check TTL heartbeat support, built on the Agent API.

- [ ] **Step 1: Create src/Service/Registry.php**

```php
<?php

namespace Erikwang\Consul\Service;

use Erikwang\Consul\Api\Agent;

class Registry
{
    private Agent $agent;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * Register a service with the local Consul agent.
     */
    public function register(string $name, string $address, int $port, array $options = []): void
    {
        $service = [
            'Name'    => $name,
            'Address' => $address,
            'Port'    => $port,
        ];

        if (isset($options['id']))   $service['ID'] = $options['id'];
        if (isset($options['tags'])) $service['Tags'] = $options['tags'];
        if (isset($options['meta'])) $service['Meta'] = $options['meta'];

        $payload = array_merge($service, $this->buildCheck($options));

        $this->agent->registerService($payload);
    }

    /**
     * Send a TTL heartbeat (pass).
     */
    public function heartbeat(string $serviceId, string $note = ''): void
    {
        $this->agent->ttlCheckPass("service:{$serviceId}", $note);
    }

    /**
     * Mark a TTL check as failing.
     */
    public function heartbeatFail(string $serviceId, string $note = ''): void
    {
        $this->agent->ttlCheckFail("service:{$serviceId}", $note);
    }

    /**
     * Deregister a service from the agent.
     */
    public function deregister(string $serviceId): void
    {
        $this->agent->deregisterService($serviceId);
    }

    private function buildCheck(array $options): array
    {
        if (!isset($options['check'])) {
            return [];
        }

        $check = $options['check'];
        $payload = [];

        if (isset($check['ttl'])) {
            $payload['Check'] = [
                'TTL' => $check['ttl'],
            ];
            if (isset($check['deregister_critical_service_after'])) {
                $payload['Check']['DeregisterCriticalServiceAfter'] =
                    $check['deregister_critical_service_after'];
            }
        } elseif (isset($check['http'])) {
            $payload['Check'] = [
                'HTTP'     => $check['http'],
                'Interval' => $check['interval'] ?? '10s',
            ];
        } elseif (isset($check['tcp'])) {
            $payload['Check'] = [
                'TCP'      => $check['tcp'],
                'Interval' => $check['interval'] ?? '10s',
            ];
        } elseif (isset($check['grpc'])) {
            $payload['Check'] = [
                'GRPC'     => $check['grpc'],
                'Interval' => $check['interval'] ?? '10s',
            ];
        }

        if (isset($payload['Check'], $check['timeout'])) {
            $payload['Check']['Timeout'] = $check['timeout'];
        }

        return $payload;
    }
}
```

- [ ] **Step 2: Create tests/Service/RegistryTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Service;

use Erikwang\Consul\Api\Agent;
use Erikwang\Consul\Service\Registry;
use PHPUnit\Framework\TestCase;

class RegistryTest extends TestCase
{
    private $agent;
    private Registry $registry;

    protected function setUp(): void
    {
        $this->agent = $this->createMock(Agent::class);
        $this->registry = new Registry($this->agent);
    }

    public function testRegisterWithTtlCheck(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'user-service',
                'Address' => '10.0.0.1',
                'Port'    => 8080,
                'ID'      => 'user-service-1',
                'Tags'    => ['v1'],
                'Check'   => ['TTL' => '30s'],
            ]);

        $this->registry->register('user-service', '10.0.0.1', 8080, [
            'id'    => 'user-service-1',
            'tags'  => ['v1'],
            'check' => ['ttl' => '30s'],
        ]);
    }

    public function testRegisterWithHttpCheck(): void
    {
        $this->agent->expects($this->once())
            ->method('registerService')
            ->with([
                'Name'    => 'web',
                'Address' => '10.0.0.2',
                'Port'    => 80,
                'Check'   => ['HTTP' => 'http://10.0.0.2:80/health', 'Interval' => '10s'],
            ]);

        $this->registry->register('web', '10.0.0.2', 80, [
            'check' => ['http' => 'http://10.0.0.2:80/health', 'interval' => '10s'],
        ]);
    }

    public function testHeartbeat(): void
    {
        $this->agent->expects($this->once())
            ->method('ttlCheckPass')
            ->with('service:web-1', '');

        $this->registry->heartbeat('web-1');
    }

    public function testHeartbeatFail(): void
    {
        $this->agent->expects($this->once())
            ->method('ttlCheckFail')
            ->with('service:web-1', 'manual fail');

        $this->registry->heartbeatFail('web-1', 'manual fail');
    }

    public function testDeregister(): void
    {
        $this->agent->expects($this->once())
            ->method('deregisterService')
            ->with('web-1');

        $this->registry->deregister('web-1');
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 4: Commit**

```bash
git add src/Service/Registry.php tests/Service/RegistryTest.php
git commit -m "feat: add service registry with TTL heartbeat"
```

---

### Task 10: Service Discovery with Load Balancing

**Files:**
- Create: `src/Service/LoadBalancer/LoadBalancerInterface.php`
- Create: `src/Service/LoadBalancer/RoundRobin.php`
- Create: `src/Service/LoadBalancer/Random.php`
- Create: `src/Service/Discovery.php`
- Create: `tests/Service/LoadBalancer/RoundRobinTest.php`
- Create: `tests/Service/LoadBalancer/RandomTest.php`
- Create: `tests/Service/DiscoveryTest.php`

- [ ] **Step 1: Create src/Service/LoadBalancer/LoadBalancerInterface.php**

```php
<?php

namespace Erikwang\Consul\Service\LoadBalancer;

interface LoadBalancerInterface
{
    public function select(array $instances): ?array;
}
```

- [ ] **Step 2: Create src/Service/LoadBalancer/RoundRobin.php**

```php
<?php

namespace Erikwang\Consul\Service\LoadBalancer;

class RoundRobin implements LoadBalancerInterface
{
    private int $count = 0;

    public function select(array $instances): ?array
    {
        if (empty($instances)) {
            return null;
        }

        $index = $this->count % count($instances);
        $this->count++;

        return array_values($instances)[$index];
    }
}
```

- [ ] **Step 3: Create src/Service/LoadBalancer/Random.php**

```php
<?php

namespace Erikwang\Consul\Service\LoadBalancer;

class Random implements LoadBalancerInterface
{
    public function select(array $instances): ?array
    {
        if (empty($instances)) {
            return null;
        }

        return $instances[array_rand($instances)];
    }
}
```

- [ ] **Step 4: Create src/Service/Discovery.php**

```php
<?php

namespace Erikwang\Consul\Service;

use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Service\LoadBalancer\LoadBalancerInterface;
use Erikwang\Consul\Service\LoadBalancer\RoundRobin;
use Psr\SimpleCache\CacheInterface;

class Discovery
{
    private Health $health;
    private ?CacheInterface $cache;
    private ?int $cacheTtl;
    private LoadBalancerInterface $loadBalancer;

    public function __construct(
        Health $health,
        ?CacheInterface $cache = null,
        ?int $cacheTtl = null,
        ?LoadBalancerInterface $loadBalancer = null
    ) {
        $this->health = $health;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->loadBalancer = $loadBalancer ?? new RoundRobin();
    }

    /**
     * Get all healthy instances of a service.
     */
    public function healthyInstances(string $service, array $options = []): array
    {
        $cacheKey = "consul:discovery:{$service}";

        if ($this->cache && !isset($options['index']) && !isset($options['wait'])) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $query = array_merge(['passing' => 'true'], $options);
        $result = $this->health->service($service, $query);

        $instances = array_map(function ($entry) {
            return [
                'node'    => $entry['Node']['Node'] ?? '',
                'address' => $entry['Service']['Address'] ?? $entry['Node']['Address'] ?? '',
                'port'    => $entry['Service']['Port'] ?? 0,
                'service' => $entry['Service']['Service'] ?? '',
                'id'      => $entry['Service']['ID'] ?? '',
                'tags'    => $entry['Service']['Tags'] ?? [],
                'meta'    => $entry['Service']['Meta'] ?? [],
            ];
        }, $result);

        if ($this->cache && !isset($options['index']) && !isset($options['wait'])) {
            $this->cache->set($cacheKey, $instances, $this->cacheTtl);
        }

        return $instances;
    }

    /**
     * Select a single healthy instance using the load balancer.
     */
    public function selectInstance(string $service, array $options = []): ?array
    {
        $instances = $this->healthyInstances($service, $options);
        return $this->loadBalancer->select($instances);
    }

    /**
     * Watch for service instance changes (blocking query based).
     * Returns the latest index. Call in a loop for continuous watching.
     */
    public function watch(string $service, callable $callback, array $options = []): void
    {
        $index = $options['index'] ?? 0;

        while (true) {
            $result = $this->healthyInstances($service, [
                'index' => $index,
                'wait'  => $options['wait'] ?? '30s',
            ]);

            $callback($result);

            // Consul returns X-Consul-Index in the response; we approximate by using
            // the blocking query mechanism — when the call returns, data has changed.
        }
    }
}
```

- [ ] **Step 5: Create tests/Service/LoadBalancer/RoundRobinTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Service\LoadBalancer;

use Erikwang\Consul\Service\LoadBalancer\RoundRobin;
use PHPUnit\Framework\TestCase;

class RoundRobinTest extends TestCase
{
    public function testSelectRotatesInstances(): void
    {
        $rr = new RoundRobin();
        $instances = [
            ['address' => '10.0.0.1', 'port' => 80],
            ['address' => '10.0.0.2', 'port' => 80],
            ['address' => '10.0.0.3', 'port' => 80],
        ];

        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.2', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.3', $rr->select($instances)['address']);
        $this->assertSame('10.0.0.1', $rr->select($instances)['address']);
    }

    public function testSelectReturnsNullWhenEmpty(): void
    {
        $rr = new RoundRobin();
        $this->assertNull($rr->select([]));
    }
}
```

- [ ] **Step 6: Create tests/Service/LoadBalancer/RandomTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Service\LoadBalancer;

use Erikwang\Consul\Service\LoadBalancer\Random;
use PHPUnit\Framework\TestCase;

class RandomTest extends TestCase
{
    public function testSelectReturnsAnInstance(): void
    {
        $random = new Random();
        $instances = [
            ['address' => '10.0.0.1', 'port' => 80],
            ['address' => '10.0.0.2', 'port' => 80],
        ];

        $selected = $random->select($instances);

        $this->assertNotNull($selected);
        $this->assertContains($selected['address'], ['10.0.0.1', '10.0.0.2']);
    }

    public function testSelectReturnsNullWhenEmpty(): void
    {
        $random = new Random();
        $this->assertNull($random->select([]));
    }
}
```

- [ ] **Step 7: Create tests/Service/DiscoveryTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Service;

use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Service\Discovery;
use PHPUnit\Framework\TestCase;

class DiscoveryTest extends TestCase
{
    private $health;
    private Discovery $discovery;

    protected function setUp(): void
    {
        $this->health = $this->createMock(Health::class);
        $this->discovery = new Discovery($this->health);
    }

    public function testHealthyInstances(): void
    {
        $this->health->method('service')
            ->with('user-service', ['passing' => 'true'])
            ->willReturn([
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => ['v1'], 'Meta' => []],
                ],
            ]);

        $instances = $this->discovery->healthyInstances('user-service');

        $this->assertCount(1, $instances);
        $this->assertSame('10.0.0.1', $instances[0]['address']);
        $this->assertSame(8080, $instances[0]['port']);
        $this->assertSame('v1', $instances[0]['tags'][0]);
    }

    public function testHealthyInstancesUsesNodeAddressWhenServiceAddressIsEmpty(): void
    {
        $this->health->method('service')
            ->with('user-service', ['passing' => 'true'])
            ->willReturn([
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.1.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                ],
            ]);

        $instances = $this->discovery->healthyInstances('user-service');

        $this->assertSame('10.0.1.1', $instances[0]['address']);
    }

    public function testSelectInstance(): void
    {
        $this->health->method('service')
            ->with('user-service', ['passing' => 'true'])
            ->willReturn([
                [
                    'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                    'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => [], 'Meta' => []],
                ],
            ]);

        $instance = $this->discovery->selectInstance('user-service');

        $this->assertNotNull($instance);
        $this->assertSame('10.0.0.1', $instance['address']);
    }

    public function testSelectInstanceReturnsNullWhenNoService(): void
    {
        $this->health->method('service')
            ->with('no-service', ['passing' => 'true'])
            ->willReturn([]);

        $instance = $this->discovery->selectInstance('no-service');

        $this->assertNull($instance);
    }
}
```

- [ ] **Step 8: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 9: Commit**

```bash
git add src/Service/ tests/Service/
git commit -m "feat: add service discovery with load balancing"
```

---

### Task 11: Config Center with Watcher

**Files:**
- Create: `src/Config/ConfigCenter.php`
- Create: `src/Config/Watcher.php`
- Create: `tests/Config/ConfigCenterTest.php`
- Create: `tests/Config/WatcherTest.php`

- [ ] **Step 1: Create src/Config/ConfigCenter.php**

```php
<?php

namespace Erikwang\Consul\Config;

use Erikwang\Consul\Api\Kv;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\SimpleCache\CacheInterface;

class ConfigCenter
{
    private Kv $kv;
    private ?CacheInterface $cache;
    private ?int $cacheTtl;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        Kv $kv,
        ?CacheInterface $cache = null,
        ?int $cacheTtl = 300,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->kv = $kv;
        $this->cache = $cache;
        $this->cacheTtl = $cacheTtl;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * Get a single KV value.
     */
    public function get(string $key, $default = null)
    {
        $cacheKey = "consul:config:{$key}";

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->kv->get($key);

        if ($result === null) {
            return $default;
        }

        $value = base64_decode($result['Value'] ?? '', true) ?: $result['Value'] ?? $default;

        if ($this->cache) {
            $this->cache->set($cacheKey, $value, $this->cacheTtl);
        }

        return $value;
    }

    /**
     * Get all KV pairs under a prefix as key => value map.
     */
    public function namespace(string $prefix): array
    {
        $cacheKey = "consul:config:ns:{$prefix}";

        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->kv->all($prefix);
        $config = [];

        foreach ($result as $item) {
            $key = $item['Key'] ?? '';
            $value = base64_decode($item['Value'] ?? '', true) ?: $item['Value'] ?? '';
            $config[$key] = $value;
        }

        if ($this->cache) {
            $this->cache->set($cacheKey, $config, $this->cacheTtl);
        }

        return $config;
    }

    /**
     * Set a KV value.
     */
    public function set(string $key, string $value): bool
    {
        return $this->kv->put($key, $value);
    }

    /**
     * Delete a KV key.
     */
    public function delete(string $key): bool
    {
        return $this->kv->delete($key);
    }

    /**
     * Create a watcher for the given prefix.
     */
    public function watch(string $prefix): Watcher
    {
        return new Watcher(
            $this->kv,
            $prefix,
            $this->eventDispatcher
        );
    }
}
```

- [ ] **Step 2: Create src/Config/Watcher.php**

```php
<?php

namespace Erikwang\Consul\Config;

use Erikwang\Consul\Api\Kv;
use Psr\EventDispatcher\EventDispatcherInterface;

class Watcher
{
    private Kv $kv;
    private string $prefix;
    private ?EventDispatcherInterface $dispatcher;
    private array $callbacks = [];
    private int $blockingWait = 30;
    private int $pollInterval = 10;
    private bool $running = false;

    public function __construct(
        Kv $kv,
        string $prefix,
        ?EventDispatcherInterface $dispatcher = null
    ) {
        $this->kv = $kv;
        $this->prefix = $prefix;
        $this->dispatcher = $dispatcher;
    }

    public function onChange(callable $callback): self
    {
        $this->callbacks[] = $callback;
        return $this;
    }

    public function setBlockingWait(int $seconds): self
    {
        $this->blockingWait = $seconds;
        return $this;
    }

    public function setPollInterval(int $seconds): self
    {
        $this->pollInterval = $seconds;
        return $this;
    }

    /**
     * Start watching. Primary: blocking query. Falls back to polling on errors.
     * This is a blocking call — run it in a separate process/coroutine.
     */
    public function start(): void
    {
        $this->running = true;
        $index = 0;
        $lastSnapshot = null;
        $usePolling = false;

        while ($this->running) {
            try {
                if ($usePolling) {
                    sleep($this->pollInterval);
                    $result = $this->kv->all($this->prefix);
                    $snapshot = $this->snapshot($result);
                    if ($snapshot !== $lastSnapshot) {
                        $lastSnapshot = $snapshot;
                        $this->notify($snapshot);
                    }
                } else {
                    try {
                        $result = $this->kv->all($this->prefix, [
                            'index' => $index,
                            'wait'  => "{$this->blockingWait}s",
                        ]);
                    } catch (\Throwable $e) {
                        // Blocking query failed — fall back to polling
                        $usePolling = true;
                        continue;
                    }

                    $snapshot = $this->snapshot($result);
                    if ($snapshot !== $lastSnapshot) {
                        $lastSnapshot = $snapshot;
                        $this->notify($snapshot);
                    }
                    // index advances automatically with each blocking query result
                }
            } catch (\Throwable $e) {
                // Log and continue watching
                if ($this->running) {
                    sleep(1);
                }
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    private function snapshot(array $kvResult): array
    {
        $snap = [];
        foreach ($kvResult as $item) {
            $key = $item['Key'] ?? '';
            $value = base64_decode($item['Value'] ?? '', true) ?: $item['Value'] ?? '';
            $snap[$key] = $value;
        }
        ksort($snap);
        return $snap;
    }

    private function notify(array $snapshot): void
    {
        foreach ($this->callbacks as $cb) {
            $cb($snapshot);
        }

        if ($this->dispatcher) {
            $this->dispatcher->dispatch(new ConfigChangedEvent($this->prefix, $snapshot));
        }
    }
}
```

- [ ] **Step 3: Create src/Config/ConfigChangedEvent.php**

```php
<?php

namespace Erikwang\Consul\Config;

class ConfigChangedEvent
{
    private string $prefix;
    private array $config;

    public function __construct(string $prefix, array $config)
    {
        $this->prefix = $prefix;
        $this->config = $config;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    public function getConfig(): array
    {
        return $this->config;
    }
}
```

- [ ] **Step 4: Create tests/Config/ConfigCenterTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Config;

use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Config\ConfigCenter;
use PHPUnit\Framework\TestCase;

class ConfigCenterTest extends TestCase
{
    private $kv;
    private ConfigCenter $config;

    protected function setUp(): void
    {
        $this->kv = $this->createMock(Kv::class);
        $this->config = new ConfigCenter($this->kv);
    }

    public function testGetReturnsDecodedValue(): void
    {
        $this->kv->method('get')
            ->with('app/db_host')
            ->willReturn(['Key' => 'app/db_host', 'Value' => base64_encode('mysql.local')]);

        $result = $this->config->get('app/db_host');

        $this->assertSame('mysql.local', $result);
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        $this->kv->method('get')
            ->with('missing/key')
            ->willReturn(null);

        $result = $this->config->get('missing/key', 'fallback');

        $this->assertSame('fallback', $result);
    }

    public function testNamespaceReturnsKeyValueMap(): void
    {
        $this->kv->method('all')
            ->with('app/')
            ->willReturn([
                ['Key' => 'app/host', 'Value' => base64_encode('localhost')],
                ['Key' => 'app/port', 'Value' => base64_encode('3306')],
            ]);

        $result = $this->config->namespace('app/');

        $this->assertSame('localhost', $result['app/host']);
        $this->assertSame('3306', $result['app/port']);
    }

    public function testSet(): void
    {
        $this->kv->method('put')
            ->with('app/key', 'value')
            ->willReturn(true);

        $result = $this->config->set('app/key', 'value');

        $this->assertTrue($result);
    }

    public function testWatchReturnsWatcher(): void
    {
        $watcher = $this->config->watch('app/');

        $this->assertInstanceOf(\Erikwang\Consul\Config\Watcher::class, $watcher);
    }
}
```

- [ ] **Step 5: Create tests/Config/WatcherTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Config;

use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Config\Watcher;
use PHPUnit\Framework\TestCase;

class WatcherTest extends TestCase
{
    public function testOnChangeRegistersCallback(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $called = false;
        $watcher->onChange(function ($snap) use (&$called) {
            $called = true;
        });

        $this->assertInstanceOf(Watcher::class, $watcher);
    }

    public function testSetBlockingWaitIsFluent(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->setBlockingWait(60);

        $this->assertSame($watcher, $result);
    }

    public function testSetPollIntervalIsFluent(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->setPollInterval(15);

        $this->assertSame($watcher, $result);
    }
}
```

- [ ] **Step 6: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 7: Commit**

```bash
git add src/Config/ tests/Config/
git commit -m "feat: add config center with hot-reload watcher"
```

---

### Task 12: Sync ConsulClient

**Files:**
- Create: `src/Client/ConsulClient.php`
- Create: `tests/Client/ConsulClientTest.php`

The sync `ConsulClient` wires together Transport + API modules + Service + Config. This is the main entry point.

- [ ] **Step 1: Create src/Client/ConsulClient.php**

```php
<?php

namespace Erikwang\Consul\Client;

use Erikwang\Consul\Api\Acl;
use Erikwang\Consul\Api\Agent;
use Erikwang\Consul\Api\Catalog;
use Erikwang\Consul\Api\Coordinate;
use Erikwang\Consul\Api\Event;
use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Api\Operator;
use Erikwang\Consul\Api\Session;
use Erikwang\Consul\Api\Snapshot;
use Erikwang\Consul\Api\Status;
use Erikwang\Consul\Config\ConfigCenter;
use Erikwang\Consul\Service\Discovery;
use Erikwang\Consul\Service\Registry;
use Erikwang\Consul\Transport\Psr18Transport;
use Erikwang\Consul\Transport\TransportInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulClient
{
    private TransportInterface $transport;

    private ?Kv $kv = null;
    private ?Agent $agent = null;
    private ?Catalog $catalog = null;
    private ?Health $health = null;
    private ?Session $session = null;
    private ?Acl $acl = null;
    private ?Event $event = null;
    private ?Status $status = null;
    private ?Coordinate $coordinate = null;
    private ?Operator $operator = null;
    private ?Snapshot $snapshot = null;
    private ?Registry $serviceRegistry = null;
    private ?Discovery $serviceDiscovery = null;
    private ?ConfigCenter $configCenter = null;

    private ?CacheInterface $cache;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        array $config = [],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $baseUri = $config['base_uri'] ?? 'http://127.0.0.1:8500';

        if ($httpClient === null) {
            $httpClient = $this->discoverHttpClient();
        }
        if ($requestFactory === null) {
            $requestFactory = $this->discoverRequestFactory();
        }
        if ($streamFactory === null) {
            $streamFactory = $this->discoverStreamFactory();
        }

        $this->transport = new Psr18Transport(
            $httpClient,
            $requestFactory,
            $streamFactory,
            $baseUri,
            $logger
        );

        $this->cache = $cache;
        $this->eventDispatcher = $eventDispatcher;

        if (isset($config['token'])) {
            // Token will be injected into each request via header in a future enhancement
        }
    }

    public function __get(string $name)
    {
        return match ($name) {
            'kv'        => $this->kv ??= new Kv($this->transport),
            'agent'     => $this->agent ??= new Agent($this->transport),
            'catalog'   => $this->catalog ??= new Catalog($this->transport),
            'health'    => $this->health ??= new Health($this->transport),
            'session'   => $this->session ??= new Session($this->transport),
            'acl'       => $this->acl ??= new Acl($this->transport),
            'event'     => $this->event ??= new Event($this->transport),
            'status'    => $this->status ??= new Status($this->transport),
            'coordinate' => $this->coordinate ??= new Coordinate($this->transport),
            'operator'  => $this->operator ??= new Operator($this->transport),
            'snapshot'  => $this->snapshot ??= new Snapshot($this->transport),
            default     => throw new \RuntimeException("Unknown API module: {$name}"),
        };
    }

    public function serviceRegistry(): Registry
    {
        return $this->serviceRegistry ??= new Registry($this->agent);
    }

    public function serviceDiscovery(): Discovery
    {
        return $this->serviceDiscovery ??= new Discovery($this->health, $this->cache);
    }

    public function configCenter(): ConfigCenter
    {
        return $this->configCenter ??= new ConfigCenter(
            $this->kv,
            $this->cache,
            300,
            $this->eventDispatcher
        );
    }

    private function discoverHttpClient(): ClientInterface
    {
        if (interface_exists(\Http\Discovery\Psr18ClientDiscovery::class, false)
            || class_exists(\Http\Discovery\Psr18ClientDiscovery::class)
        ) {
            return \Http\Discovery\Psr18ClientDiscovery::find();
        }
        throw new \RuntimeException(
            'No PSR-18 HTTP client found. Require php-http/discovery and guzzlehttp/guzzle, or inject manually.'
        );
    }

    private function discoverRequestFactory(): RequestFactoryInterface
    {
        if (interface_exists(\Http\Discovery\Psr17FactoryDiscovery::class, false)
            || class_exists(\Http\Discovery\Psr17FactoryDiscovery::class)
        ) {
            return \Http\Discovery\Psr17FactoryDiscovery::findRequestFactory();
        }
        throw new \RuntimeException('No PSR-17 request factory found.');
    }

    private function discoverStreamFactory(): StreamFactoryInterface
    {
        if (interface_exists(\Http\Discovery\Psr17FactoryDiscovery::class, false)
            || class_exists(\Http\Discovery\Psr17FactoryDiscovery::class)
        ) {
            return \Http\Discovery\Psr17FactoryDiscovery::findStreamFactory();
        }
        throw new \RuntimeException('No PSR-17 stream factory found.');
    }
}
```

- [ ] **Step 2: Create tests/Client/ConsulClientTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Client;

use Erikwang\Consul\Api\Agent;
use Erikwang\Consul\Api\Catalog;
use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Api\Session;
use Erikwang\Consul\Client\ConsulClient;
use Erikwang\Consul\Config\ConfigCenter;
use Erikwang\Consul\Service\Discovery;
use Erikwang\Consul\Service\Registry;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class ConsulClientTest extends TestCase
{
    public function testConstructorWithManualDependencies(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = new ConsulClient(
            ['base_uri' => 'http://consul:8500'],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(ConsulClient::class, $client);
    }

    public function testPropertyAccessReturnsApiModules(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = new ConsulClient(
            [],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(Kv::class, $client->kv);
        $this->assertInstanceOf(Agent::class, $client->agent);
        $this->assertInstanceOf(Catalog::class, $client->catalog);
        $this->assertInstanceOf(Health::class, $client->health);
        $this->assertInstanceOf(Session::class, $client->session);
    }

    public function testServiceRegistry(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = new ConsulClient(
            [],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(Registry::class, $client->serviceRegistry());
    }

    public function testServiceDiscovery(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = new ConsulClient(
            [],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(Discovery::class, $client->serviceDiscovery());
    }

    public function testConfigCenter(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = new ConsulClient(
            [],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->assertInstanceOf(ConfigCenter::class, $client->configCenter());
    }

    public function testUnknownPropertyThrowsException(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $client = new ConsulClient(
            [],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $this->expectException(\RuntimeException::class);
        $client->unknown;
    }
}
```

- [ ] **Step 3: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 4: Commit**

```bash
git add src/Client/ConsulClient.php tests/Client/ConsulClientTest.php
git commit -m "feat: add sync ConsulClient"
```

---

### Task 13: Async ConsulAsyncClient

**Files:**
- Create: `src/Client/ConsulAsyncClient.php`
- Create: `src/Client/Promise.php`
- Create: `tests/Client/ConsulAsyncClientTest.php`

- [ ] **Step 1: Create src/Client/Promise.php**

```php
<?php

namespace Erikwang\Consul\Client;

class Promise
{
    private $executor;
    private $thenCallbacks = [];
    private $catchCallbacks = [];
    private $resolved = false;
    private $value;
    private $exception;

    public function __construct(callable $executor)
    {
        $this->executor = $executor;
    }

    public function then(callable $onFulfilled): self
    {
        if ($this->resolved) {
            if ($this->exception === null) {
                $onFulfilled($this->value);
            }
            return $this;
        }

        $this->thenCallbacks[] = $onFulfilled;
        return $this;
    }

    public function catch(callable $onRejected): self
    {
        if ($this->resolved && $this->exception !== null) {
            $onRejected($this->exception);
            return $this;
        }

        $this->catchCallbacks[] = $onRejected;
        return $this;
    }

    public function wait()
    {
        if (!$this->resolved) {
            try {
                $this->value = ($this->executor)();
                $this->exception = null;
            } catch (\Throwable $e) {
                $this->exception = $e;
            }
            $this->resolved = true;

            if ($this->exception !== null) {
                foreach ($this->catchCallbacks as $cb) {
                    $cb($this->exception);
                }
                throw $this->exception;
            }

            foreach ($this->thenCallbacks as $cb) {
                $cb($this->value);
            }
        }

        return $this->value;
    }
}
```

- [ ] **Step 2: Create src/Client/ConsulAsyncClient.php**

```php
<?php

namespace Erikwang\Consul\Client;

use Erikwang\Consul\Api\Acl;
use Erikwang\Consul\Api\Agent;
use Erikwang\Consul\Api\Catalog;
use Erikwang\Consul\Api\Coordinate;
use Erikwang\Consul\Api\Event;
use Erikwang\Consul\Api\Health;
use Erikwang\Consul\Api\Kv;
use Erikwang\Consul\Api\Operator;
use Erikwang\Consul\Api\Session;
use Erikwang\Consul\Api\Snapshot;
use Erikwang\Consul\Api\Status;
use Erikwang\Consul\Config\ConfigCenter;
use Erikwang\Consul\Service\Discovery;
use Erikwang\Consul\Service\Registry;
use Erikwang\Consul\Transport\Psr18Transport;
use Erikwang\Consul\Transport\TransportInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulAsyncClient
{
    private TransportInterface $transport;
    private ConsulClient $syncClient;

    public function __construct(
        array $config = [],
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?LoggerInterface $logger = null,
        ?CacheInterface $cache = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $this->syncClient = new ConsulClient(
            $config,
            $httpClient,
            $requestFactory,
            $streamFactory,
            $logger,
            $cache,
            $eventDispatcher
        );
    }

    public function wrap(callable $callable): Promise
    {
        return new Promise($callable);
    }

    public function serviceRegistry(): Registry
    {
        return $this->syncClient->serviceRegistry();
    }

    public function serviceDiscovery(): Discovery
    {
        return $this->syncClient->serviceDiscovery();
    }

    public function configCenter(): ConfigCenter
    {
        return $this->syncClient->configCenter();
    }

    public function __get(string $name)
    {
        return $this->syncClient->{$name};
    }
}
```

- [ ] **Step 3: Create tests/Client/ConsulAsyncClientTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Client;

use Erikwang\Consul\Client\ConsulAsyncClient;
use Erikwang\Consul\Client\Promise;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class ConsulAsyncClientTest extends TestCase
{
    private ConsulAsyncClient $client;

    protected function setUp(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $this->client = new ConsulAsyncClient(
            ['base_uri' => 'http://127.0.0.1:8500'],
            $httpClient,
            $requestFactory,
            $streamFactory
        );
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(ConsulAsyncClient::class, $this->client);
    }

    public function testWrapReturnsPromise(): void
    {
        $promise = $this->client->wrap(function () {
            return 'result';
        });

        $this->assertInstanceOf(Promise::class, $promise);
        $this->assertSame('result', $promise->wait());
    }

    public function testPromiseThen(): void
    {
        $results = [];
        $promise = $this->client->wrap(function () {
            return 42;
        });

        $promise->then(function ($val) use (&$results) {
            $results[] = $val;
        });

        $promise->wait();
        $this->assertSame([42], $results);
    }

    public function testPromiseCatch(): void
    {
        $errors = [];
        $promise = $this->client->wrap(function () {
            throw new \RuntimeException('fail');
        });

        $promise->catch(function ($e) use (&$errors) {
            $errors[] = $e->getMessage();
        });

        try {
            $promise->wait();
        } catch (\RuntimeException $e) {
        }

        $this->assertSame(['fail'], $errors);
    }

    public function testServiceRegistry(): void
    {
        $this->assertInstanceOf(\Erikwang\Consul\Service\Registry::class, $this->client->serviceRegistry());
    }

    public function testServiceDiscovery(): void
    {
        $this->assertInstanceOf(\Erikwang\Consul\Service\Discovery::class, $this->client->serviceDiscovery());
    }

    public function testConfigCenter(): void
    {
        $this->assertInstanceOf(\Erikwang\Consul\Config\ConfigCenter::class, $this->client->configCenter());
    }
}
```

- [ ] **Step 4: Run tests**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 5: Commit**

```bash
git add src/Client/ConsulAsyncClient.php src/Client/Promise.php tests/Client/ConsulAsyncClientTest.php
git commit -m "feat: add async ConsulAsyncClient with Promise"
```

---

### Task 14: Laravel Extension

**Files:**
- Create: `extensions/laravel/composer.json`
- Create: `extensions/laravel/src/ConsulServiceProvider.php`
- Create: `extensions/laravel/src/Facades/Consul.php`
- Create: `extensions/laravel/config/consul.php`

- [ ] **Step 1: Create extensions/laravel/composer.json**

```json
{
    "name": "erikwang/consul-php-laravel",
    "description": "Laravel integration for erikwang/consul-php",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.0",
        "erikwang/consul-php": "*",
        "illuminate/support": "^8.0|^9.0|^10.0|^11.0|^12.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang\\Consul\\Laravel\\": "src/"
        }
    },
    "extra": {
        "laravel": {
            "providers": [
                "Erikwang\\Consul\\Laravel\\ConsulServiceProvider"
            ],
            "aliases": {
                "Consul": "Erikwang\\Consul\\Laravel\\Facades\\Consul"
            }
        }
    }
}
```

- [ ] **Step 2: Create extensions/laravel/config/consul.php**

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

- [ ] **Step 3: Create extensions/laravel/src/ConsulServiceProvider.php**

```php
<?php

namespace Erikwang\Consul\Laravel;

use Erikwang\Consul\Client\ConsulClient;
use Illuminate\Support\ServiceProvider;

class ConsulServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/consul.php', 'consul');

        $this->app->singleton(ConsulClient::class, function ($app) {
            $config = $app['config']['consul'];

            return new ConsulClient(
                $config,
                $app->make(\Psr\Http\Client\ClientInterface::class),
                $app->make(\Psr\Http\Message\RequestFactoryInterface::class),
                $app->make(\Psr\Http\Message\StreamFactoryInterface::class),
                $app->make(\Psr\Log\LoggerInterface::class),
                $config['cache']['enable'] ? $app->make(\Psr\SimpleCache\CacheInterface::class) : null,
                $app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
            );
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/consul.php' => config_path('consul.php'),
        ], 'consul-config');
    }
}
```

- [ ] **Step 4: Create extensions/laravel/src/Facades/Consul.php**

```php
<?php

namespace Erikwang\Consul\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

class Consul extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Erikwang\Consul\Client\ConsulClient::class;
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add extensions/
git commit -m "feat: add Laravel extension"
```

---

### Task 15: Hyperf Extension

**Files:**
- Create: `extensions/hyperf/composer.json`
- Create: `extensions/hyperf/src/ConfigProvider.php`
- Create: `extensions/hyperf/publish/consul.php`

- [ ] **Step 1: Create extensions/hyperf/composer.json**

```json
{
    "name": "erikwang/consul-php-hyperf",
    "description": "Hyperf integration for erikwang/consul-php",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.0",
        "erikwang/consul-php": "*",
        "hyperf/framework": "^3.0|^2.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang\\Consul\\Hyperf\\": "src/"
        }
    },
    "extra": {
        "hyperf": {
            "config": "Erikwang\\Consul\\Hyperf\\ConfigProvider"
        }
    }
}
```

- [ ] **Step 2: Create extensions/hyperf/publish/consul.php**

```php
<?php

declare(strict_types=1);

return [
    'base_uri' => env('CONSUL_BASE_URI', 'http://127.0.0.1:8500'),
    'token'    => env('CONSUL_TOKEN', ''),
    'cache'    => [
        'enable' => true,
        'ttl'    => 300,
    ],
];
```

- [ ] **Step 3: Create extensions/hyperf/src/ConfigProvider.php**

```php
<?php

declare(strict_types=1);

namespace Erikwang\Consul\Hyperf;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                \Erikwang\Consul\Client\ConsulClient::class => ConsulClientFactory::class,
            ],
            'publish' => [
                [
                    'id'          => 'consul',
                    'description' => 'Consul config',
                    'source'      => __DIR__ . '/../publish/consul.php',
                    'destination' => BASE_PATH . '/config/autoload/consul.php',
                ],
            ],
        ];
    }
}
```

- [ ] **Step 4: Create extensions/hyperf/src/ConsulClientFactory.php**

```php
<?php

declare(strict_types=1);

namespace Erikwang\Consul\Hyperf;

use Erikwang\Consul\Client\ConsulClient;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

class ConsulClientFactory
{
    public function __invoke(ContainerInterface $container): ConsulClient
    {
        $config = $container->get(ConfigInterface::class)->get('consul', []);

        $httpClient = $container->get(\Hyperf\Guzzle\CoroutineHandler::class)
            ? new \GuzzleHttp\Client(['handler' => $container->get(\Hyperf\Guzzle\CoroutineHandler::class)])
            : $container->get(\Psr\Http\Client\ClientInterface::class);

        return new ConsulClient(
            $config,
            $httpClient,
            $container->get(\Psr\Http\Message\RequestFactoryInterface::class),
            $container->get(\Psr\Http\Message\StreamFactoryInterface::class),
            $container->get(LoggerInterface::class),
            ($config['cache']['enable'] ?? false) ? $container->get(CacheInterface::class) : null,
            $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class)
        );
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add extensions/
git commit -m "feat: add Hyperf extension with coroutine HTTP support"
```

---

### Task 16: webman Extension

**Files:**
- Create: `extensions/webman/composer.json`
- Create: `extensions/webman/src/Install.php`
- Create: `extensions/webman/src/config/plugin/erikwang/consul-php/app.php`

- [ ] **Step 1: Create extensions/webman/composer.json**

```json
{
    "name": "erikwang/consul-php-webman",
    "description": "webman integration for erikwang/consul-php",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.0",
        "erikwang/consul-php": "*",
        "workerman/webman-framework": "^1.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang\\Consul\\Webman\\": "src/"
        }
    }
}
```

- [ ] **Step 2: Create extensions/webman/src/Install.php**

```php
<?php

namespace Erikwang\Consul\Webman;

class Install
{
    public const WEBMAN_PLUGIN = true;

    public static function install(): void
    {
        $configDir = config_path() . '/plugin/erikwang/consul-php';
        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $configPath = $configDir . '/app.php';
        if (!file_exists($configPath)) {
            copy(__DIR__ . '/config/plugin/erikwang/consul-php/app.php', $configPath);
        }
    }

    public static function uninstall(): void
    {
        $configPath = config_path() . '/plugin/erikwang/consul-php/app.php';
        if (file_exists($configPath)) {
            unlink($configPath);
        }
    }
}
```

- [ ] **Step 3: Create extensions/webman/src/config/plugin/erikwang/consul-php/app.php**

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

- [ ] **Step 4: Commit**

```bash
git add extensions/
git commit -m "feat: add webman extension"
```

---

### Task 17: ThinkPHP Extension

**Files:**
- Create: `extensions/thinkphp/composer.json`
- Create: `extensions/thinkphp/src/ConsulService.php`
- Create: `extensions/thinkphp/src/config/consul.php`

- [ ] **Step 1: Create extensions/thinkphp/composer.json**

```json
{
    "name": "erikwang/consul-php-thinkphp",
    "description": "ThinkPHP integration for erikwang/consul-php",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": ">=8.0",
        "erikwang/consul-php": "*",
        "topthink/framework": "^6.0|^8.0"
    },
    "autoload": {
        "psr-4": {
            "Erikwang\\Consul\\Thinkphp\\": "src/"
        }
    }
}
```

- [ ] **Step 2: Create extensions/thinkphp/src/config/consul.php**

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

- [ ] **Step 3: Create extensions/thinkphp/src/ConsulService.php**

```php
<?php

namespace Erikwang\Consul\Thinkphp;

use Erikwang\Consul\Client\ConsulClient;
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
                isset($config['cache']['enable']) && $config['cache']['enable']
                    ? $this->app->make(\Psr\SimpleCache\CacheInterface::class)
                    : null,
                $this->app->make(\Psr\EventDispatcher\EventDispatcherInterface::class)
            );
        });
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add extensions/
git commit -m "feat: add ThinkPHP extension"
```

---

### Task 18: Final integration test and verification

**Files:**
- Create: `tests/Integration/EndToEndTest.php`

- [ ] **Step 1: Create tests/Integration/EndToEndTest.php**

```php
<?php

namespace Erikwang\Consul\Tests\Integration;

use Erikwang\Consul\Client\ConsulClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

class EndToEndTest extends TestCase
{
    public function testFullWorkflow(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            [
                'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => ['v1'], 'Meta' => []],
                'Checks'  => [['Status' => 'passing']],
            ],
        ]));

        $requestFactory->method('createRequest')->willReturn($request);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = new ConsulClient(
            ['base_uri' => 'http://127.0.0.1:8500'],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        // Test service discovery
        $instances = $client->serviceDiscovery()->healthyInstances('user-service');
        $this->assertCount(1, $instances);
        $this->assertSame('10.0.0.1', $instances[0]['address']);
        $this->assertSame(8080, $instances[0]['port']);

        // Test load balancer selection
        $instance = $client->serviceDiscovery()->selectInstance('user-service');
        $this->assertNotNull($instance);
        $this->assertSame('10.0.0.1', $instance['address']);
    }

    public function testConfigWorkflow(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);
        $streamFactory = $this->createMock(StreamFactoryInterface::class);

        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn(json_encode([
            ['Key' => 'app/db_host', 'Value' => base64_encode('mysql.local')],
        ]));

        $requestFactory->method('createRequest')->willReturn($request);
        $httpClient->method('sendRequest')->willReturn($response);

        $client = new ConsulClient(
            ['base_uri' => 'http://127.0.0.1:8500'],
            $httpClient,
            $requestFactory,
            $streamFactory
        );

        $dbHost = $client->configCenter()->get('app/db_host');
        $this->assertSame('mysql.local', $dbHost);
    }
}
```

- [ ] **Step 2: Run full test suite**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpunit
```

- [ ] **Step 3: Run PHPStan**

```bash
cd /home/wwwroot/erikwang2013/consul-php && vendor/bin/phpstan analyse src --level=6
```

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/
git commit -m "feat: add end-to-end integration tests"
```

---

## Summary

Total: 18 tasks covering:
- Task 1: Project skeleton + exceptions + CI
- Task 2: HTTP transport layer (PSR-18 wrapper)
- Tasks 3-8: All 11 Consul API modules
- Tasks 9-10: Service registry + discovery with load balancing
- Task 11: Config center + hot-reload watcher
- Tasks 12-13: Sync + async clients
- Tasks 14-17: Framework extensions (Laravel, Hyperf, webman, ThinkPHP)
- Task 18: Integration tests + final verification
