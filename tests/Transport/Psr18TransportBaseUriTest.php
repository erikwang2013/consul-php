<?php

namespace Erikwang2013\Consul\Tests\Transport;

use Erikwang2013\Consul\Transport\Psr18Transport;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

/**
 * base_uri 常从环境变量抄来，里面没写 scheme 或者多一个尾斜杠都很常见。
 * 断言方式：看拼出来的请求 URI，而不是反射内部字段。
 */
class Psr18TransportBaseUriTest extends TestCase
{
    private $httpClient;
    private $requestFactory;
    private $streamFactory;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);
    }

    private function expectRequestTo(string $expectedUri): void
    {
        $request = $this->createMock(RequestInterface::class);

        $this->requestFactory->expects($this->once())
            ->method('createRequest')
            ->with('GET', $expectedUri)
            ->willReturn($request);

        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn('{}');

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($stream);

        $this->httpClient->method('sendRequest')->willReturn($response);
    }

    private function transport(string $baseUri): Psr18Transport
    {
        return new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            $baseUri
        );
    }

    public function testDefaultBaseUriIsLocalAgent(): void
    {
        $this->expectRequestTo('http://127.0.0.1:8500/v1/status/leader');

        (new Psr18Transport($this->httpClient, $this->requestFactory, $this->streamFactory))
            ->get('/v1/status/leader');
    }

    public function testMissingSchemeGetsHttpPrefix(): void
    {
        $this->expectRequestTo('http://127.0.0.1:8500/v1/status/leader');

        $this->transport('127.0.0.1:8500')->get('/v1/status/leader');
    }

    public function testTrailingSlashAndSpacesAreTrimmed(): void
    {
        $this->expectRequestTo('http://consul.local:8500/v1/status/leader');

        $this->transport('  http://consul.local:8500/  ')->get('/v1/status/leader');
    }

    public function testExplicitSchemeIsKept(): void
    {
        $this->expectRequestTo('https://consul.local/v1/status/leader');

        $this->transport('https://consul.local')->get('/v1/status/leader');
    }

    public function testEmptyBaseUriThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Consul base_uri 不能为空');

        $this->transport('');
    }

    public function testWhitespaceOnlyBaseUriThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->transport('   ');
    }
}
