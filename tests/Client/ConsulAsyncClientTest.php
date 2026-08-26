<?php

namespace Erikwang2013\Consul\Tests\Client;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Api\Snapshot;
use Erikwang2013\Consul\Client\ConsulAsyncClient;
use Erikwang2013\Consul\Client\Promise;
use Erikwang2013\Consul\Exception\ConsulException;
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
        $this->assertInstanceOf(\Erikwang2013\Consul\Service\Registry::class, $this->client->serviceRegistry());
    }

    public function testServiceDiscovery(): void
    {
        $this->assertInstanceOf(\Erikwang2013\Consul\Service\Discovery::class, $this->client->serviceDiscovery());
    }

    public function testConfigCenter(): void
    {
        $this->assertInstanceOf(\Erikwang2013\Consul\Config\ConfigCenter::class, $this->client->configCenter());
    }

    public function testMagicGetDelegatesToSyncClient(): void
    {
        $this->assertInstanceOf(Kv::class, $this->client->kv);
        $this->assertInstanceOf(Snapshot::class, $this->client->snapshot);
    }

    public function testUnknownPropertyThrowsConsulException(): void
    {
        $this->expectException(ConsulException::class);
        $this->client->unknown;
    }

    public function testWrapRunsExecutorLazilyOnWait(): void
    {
        $executed = false;
        $promise = $this->client->wrap(function () use (&$executed) {
            $executed = true;
            return 'done';
        });

        $this->assertFalse($executed);
        $this->assertSame('done', $promise->wait());
        $this->assertTrue($executed);
    }
}
