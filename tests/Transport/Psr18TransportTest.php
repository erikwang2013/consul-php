<?php

namespace Erikwang\Consul\Tests\Transport;

use Erikwang\Consul\Exception\AccessDeniedException;
use Erikwang\Consul\Exception\ClientException;
use Erikwang\Consul\Exception\ConsulRequestException;
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

    private function createStreamWithContent(string $content): StreamInterface
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($content);
        return $stream;
    }

    public function testGetRequestReturnsDecodedJson(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->createStreamWithContent('{"key":"value"}'));

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
        $response->method('getBody')->willReturn($this->createStreamWithContent(''));

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
        $response->method('getBody')->willReturn($this->createStreamWithContent('internal error'));

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
        $response->method('getBody')->willReturn($this->createStreamWithContent('{"success":true}'));

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

    public function test403ThrowsAccessDeniedException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(403);
        $response->method('getBody')->willReturn($this->createStreamWithContent('denied'));

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(AccessDeniedException::class);
        $this->transport->get('/v1/kv/secret');
    }

    public function test400ThrowsConsulRequestException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(400);
        $response->method('getBody')->willReturn($this->createStreamWithContent('bad request'));

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $this->expectException(ConsulRequestException::class);
        $this->transport->get('/v1/kv');
    }

    public function testGetWithQueryParameters(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->createStreamWithContent('{"result":"ok"}'));

        $this->requestFactory->method('createRequest')
            ->with('GET', 'http://127.0.0.1:8500/v1/kv/test?dc=dc1&recurse=true')
            ->willReturn($request);

        $this->httpClient->method('sendRequest')->with($request)->willReturn($response);

        $result = $this->transport->get('/v1/kv/test', ['dc' => 'dc1', 'recurse' => 'true']);

        $this->assertSame(['result' => 'ok'], $result);
    }

    public function testPutRequest(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->createStreamWithContent('{"ok":true}'));

        $request->method('withBody')->willReturn($request);
        $request->method('withHeader')->willReturn($request);

        $this->requestFactory->method('createRequest')
            ->with('PUT', 'http://127.0.0.1:8500/v1/kv/key')
            ->willReturn($request);

        $stream = $this->createMock(StreamInterface::class);
        $this->streamFactory->method('createStream')
            ->with('{"data":1}')
            ->willReturn($stream);

        $this->httpClient->method('sendRequest')->willReturn($response);

        $result = $this->transport->put('/v1/kv/key', ['data' => 1]);

        $this->assertSame(['ok' => true], $result);
    }

    public function testDeleteRequest(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->createStreamWithContent(''));

        $this->requestFactory->method('createRequest')
            ->with('DELETE', 'http://127.0.0.1:8500/v1/kv/key')
            ->willReturn($request);

        $this->httpClient->method('sendRequest')->with($request)->willReturn($response);

        $result = $this->transport->delete('/v1/kv/key');

        $this->assertSame([], $result);
    }

    public function testGetDoesNotSetContentTypeHeader(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getBody')->willReturn($this->createStreamWithContent('[]'));

        $request->expects($this->never())->method('withBody');
        $request->expects($this->never())->method('withHeader');

        $this->requestFactory->method('createRequest')->willReturn($request);
        $this->httpClient->method('sendRequest')->willReturn($response);

        $result = $this->transport->get('/v1/status/leader');

        $this->assertSame([], $result);
    }
}
