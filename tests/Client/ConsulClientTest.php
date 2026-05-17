<?php

namespace Erikwang2013\Consul\Tests\Client;

use Erikwang2013\Consul\Api\Agent;
use Erikwang2013\Consul\Api\Catalog;
use Erikwang2013\Consul\Api\Health;
use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Api\Session;
use Erikwang2013\Consul\Client\ConsulClient;
use Erikwang2013\Consul\Config\ConfigCenter;
use Erikwang2013\Consul\Service\Discovery;
use Erikwang2013\Consul\Service\Registry;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class ConsulClientTest extends TestCase
{
    private $httpClient;
    private $requestFactory;
    private $streamFactory;
    private ConsulClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);

        $this->client = new ConsulClient(
            ['base_uri' => 'http://consul:8500'],
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );
    }

    public function testConstructorWithManualDependencies(): void
    {
        $this->assertInstanceOf(ConsulClient::class, $this->client);
    }

    public function testPropertyAccessReturnsApiModules(): void
    {
        $this->assertInstanceOf(Kv::class, $this->client->kv);
        $this->assertInstanceOf(Agent::class, $this->client->agent);
        $this->assertInstanceOf(Catalog::class, $this->client->catalog);
        $this->assertInstanceOf(Health::class, $this->client->health);
        $this->assertInstanceOf(Session::class, $this->client->session);
    }

    public function testServiceRegistry(): void
    {
        $this->assertInstanceOf(Registry::class, $this->client->serviceRegistry());
    }

    public function testServiceDiscovery(): void
    {
        $this->assertInstanceOf(Discovery::class, $this->client->serviceDiscovery());
    }

    public function testConfigCenter(): void
    {
        $this->assertInstanceOf(ConfigCenter::class, $this->client->configCenter());
    }

    public function testUnknownPropertyThrowsException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->client->unknown;
    }
}
