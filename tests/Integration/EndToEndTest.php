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

        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $stream->method('__toString')->willReturn(json_encode([
            [
                'Node'    => ['Node' => 'node1', 'Address' => '10.0.0.1'],
                'Service' => ['Service' => 'user-service', 'Address' => '10.0.0.1', 'Port' => 8080, 'ID' => 'user-1', 'Tags' => ['v1'], 'Meta' => []],
                'Checks'  => [['Status' => 'passing']],
            ],
        ]));
        $response->method('getBody')->willReturn($stream);

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

        $stream = $this->createMock(\Psr\Http\Message\StreamInterface::class);
        $stream->method('__toString')->willReturn(json_encode([
            ['Key' => 'app/db_host', 'Value' => base64_encode('mysql.local')],
        ]));
        $response->method('getBody')->willReturn($stream);

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
