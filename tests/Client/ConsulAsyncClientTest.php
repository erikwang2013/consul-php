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
