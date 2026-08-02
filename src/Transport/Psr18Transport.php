<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Transport;

use Erikwang2013\Consul\Exception\AccessDeniedException;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Exception\ConsulRequestException;
use Erikwang2013\Consul\Exception\ServerException;
use Erikwang2013\Consul\Exception\UnauthorizedException;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

class Psr18Transport implements TransportInterface
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private string $baseUri;
    private ?string $token;
    private LoggerInterface $logger;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri = 'http://127.0.0.1:8500',
        ?string $token = null,
        ?LoggerInterface $logger = null
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = rtrim($baseUri, '/');
        $this->token = $token;
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

    public function getRaw(string $path, array $query = []): string
    {
        return $this->sendRaw('GET', $path, '', $query);
    }

    public function putRaw(string $path, string $body, array $query = []): array
    {
        return $this->sendRaw('PUT', $path, $body, $query, true);
    }

    public function getWithHeaders(string $path, array $query = []): array
    {
        $uri = $this->baseUri . $path;
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest('GET', $uri);

        if ($this->token !== null) {
            $request = $request->withHeader('X-Consul-Token', $this->token);
        }

        $this->logger->debug("Consul request with headers: GET $uri");

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable $e) {
            throw new ClientException("HTTP transport error: " . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();

        try {
            $contents = (string) $response->getBody();
        } catch (RuntimeException $e) {
            throw new ClientException("Failed to read response body: " . $e->getMessage(), 0, $e);
        }

        $this->checkStatus($statusCode, $contents);

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = $values[0] ?? '';
        }

        $body = $this->decodeBody($contents);
        return ['headers' => $headers, 'body' => $body];
    }

    private function sendRaw(string $method, string $path, string $body, array $query = [], bool $decodeJson = false): array|string
    {
        $uri = $this->baseUri . $path;
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest($method, $uri);

        if ($this->token !== null) {
            $request = $request->withHeader('X-Consul-Token', $this->token);
        }

        if ($body !== '') {
            $stream = $this->streamFactory->createStream($body);
            $request = $request->withBody($stream)
                ->withHeader('Content-Type', 'application/octet-stream');
        }

        $this->logger->debug("Consul raw request: $method $uri");

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable $e) {
            throw new ClientException("HTTP transport error: " . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();

        try {
            $contents = (string) $response->getBody();
        } catch (RuntimeException $e) {
            throw new ClientException("Failed to read response body: " . $e->getMessage(), 0, $e);
        }

        $this->checkStatus($statusCode, $contents);

        if ($decodeJson) {
            return $this->decodeBody($contents);
        }
        return $contents;
    }

    private function checkStatus(int $statusCode, string $contents): void
    {
        if ($statusCode >= 500) {
            throw new ServerException("Consul server error [$statusCode]: $contents", $statusCode);
        }

        $class = match ($statusCode) {
            401 => UnauthorizedException::class,
            403 => AccessDeniedException::class,
            404 => NotFoundException::class,
            default => null,
        };

        if ($class !== null) {
            throw new $class("Consul request error [$statusCode]: $contents", $statusCode);
        }

        if ($statusCode >= 400) {
            throw new ConsulRequestException("Consul request error [$statusCode]: $contents", $statusCode);
        }
    }

    /**
     * Decode the response body, wrapping scalar JSON values in ['body' => $value]
     * so callers can consistently access $result['body'] for top-level scalars
     * (e.g. Kv::put() returns bool, Status::leader() returns string).
     */
    private function decodeBody(string $contents): array
    {
        if ($contents === '') {
            return [];
        }

        $decoded = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientException("Failed to decode Consul response: " . json_last_error_msg());
        }
        if (!is_array($decoded)) {
            return ['body' => $decoded];
        }
        return $decoded;
    }

    private function request(string $method, string $path, array $body = [], array $query = []): array
    {
        $uri = $this->baseUri . $path;
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest($method, $uri);

        if ($this->token !== null) {
            $request = $request->withHeader('X-Consul-Token', $this->token);
        }

        if (!empty($body)) {
            $json = json_encode($body);
            if ($json === false) {
                throw new ConsulRequestException('Failed to encode request body: ' . json_last_error_msg());
            }
            $stream = $this->streamFactory->createStream($json);
            $request = $request->withBody($stream)
                ->withHeader('Content-Type', 'application/json');
        }

        $this->logger->debug("Consul request: $method $uri");

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (Throwable $e) {
            throw new ClientException("HTTP transport error: " . $e->getMessage(), 0, $e);
        }

        $statusCode = $response->getStatusCode();

        try {
            $contents = (string) $response->getBody();
        } catch (RuntimeException $e) {
            throw new ClientException("Failed to read response body: " . $e->getMessage(), 0, $e);
        }

        $this->checkStatus($statusCode, $contents);

        return $this->decodeBody($contents);
    }
}
