<?php

namespace Erikwang2013\Consul\Tests\Transport;

use Erikwang2013\Consul\Transport\Psr18Transport;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class Psr18TransportRawTest extends TestCase
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

    private function createStreamWithContent(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);
        return $stream;
    }

    private function stubResponse(int $statusCode, string $body): ResponseInterface
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($this->createStreamWithContent($body));
        return $response;
    }

    public function testBaseUriTrailingSlashIsTrimmed(): void
    {
        $transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            'http://127.0.0.1:8500/'
        );

        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')
            ->with('GET', 'http://127.0.0.1:8500/v1/status/leader')
            ->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, '[]'));

        $result = $transport->get('/v1/status/leader');

        $this->assertSame([], $result);
    }

    public function testScalarJsonResponseIsWrappedInBodyKey(): void
    {
        $transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, 'true'));

        $result = $transport->get('/v1/kv/key');

        $this->assertSame(['body' => true], $result);
    }

    public function testStringScalarResponseIsWrappedInBodyKey(): void
    {
        $transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, '"leader-1"'));

        $result = $transport->get('/v1/status/leader');

        $this->assertSame(['body' => 'leader-1'], $result);
    }

    public function testPutRawEmptyBodyDoesNotSetContentTypeHeader(): void
    {
        $transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory
        );

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->never())->method('withBody');
        $request->expects($this->never())->method('withHeader');

        $this->requestFactory->method('createRequest')
            ->with('PUT', 'http://127.0.0.1:8500/v1/agent/check/pass/abc')
            ->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, '[]'));

        $result = $transport->putRaw('/v1/agent/check/pass/abc', '');

        $this->assertSame([], $result);
    }

    public function testPutRawWithTokenSetsHeader(): void
    {
        $transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            'http://127.0.0.1:8500',
            'secret-token'
        );

        $request = $this->createMock(RequestInterface::class);
        $request->method('withBody')->willReturn($request);
        $request->expects($this->atLeast(2))
            ->method('withHeader')
            ->willReturn($request);

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->streamFactory->method('createStream')->willReturn($this->createMock(StreamInterface::class));
        $this->httpClient->method('sendRequest')->willReturn($this->stubResponse(200, '[]'));

        $result = $transport->putRaw('/v1/kv/key', 'data');

        $this->assertSame([], $result);
    }

    public function testGetWithHeadersSetsTokenHeaderAndPreservesHeaderValues(): void
    {
        $transport = new Psr18Transport(
            $this->httpClient,
            $this->requestFactory,
            $this->streamFactory,
            'http://127.0.0.1:8500',
            'abc123'
        );

        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('withHeader')
            ->with('X-Consul-Token', 'abc123')
            ->willReturn($request);

        $this->requestFactory->method('createRequest')->willReturn($request);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->createStreamWithContent('[]'));
        $response->method('getHeaders')->willReturn([
            'X-Consul-Index' => ['7', 'ignored-duplicate'],
            'X-Consul-Query-Backend' => ['streaming'],
        ]);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $result = $transport->getWithHeaders('/v1/kv/key', ['index' => '0']);

        $this->assertSame('7', $result['headers']['X-Consul-Index']);
        $this->assertSame('streaming', $result['headers']['X-Consul-Query-Backend']);
        $this->assertSame([], $result['body']);
    }
}
