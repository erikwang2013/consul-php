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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Throwable;

class Psr18Transport implements TransportInterface
{
    private const MAX_ERROR_BODY_LENGTH = 200;

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
        $response = $this->sendRequest('GET', $path, '', '', $query);
        $contents = $this->readBody($response);
        $this->checkStatus($response->getStatusCode(), $contents);

        $headers = [];
        foreach ($response->getHeaders() as $name => $values) {
            $headers[$name] = $values[0] ?? '';
        }

        return ['headers' => $headers, 'body' => $this->decodeBody($contents)];
    }

    private function sendRaw(string $method, string $path, string $body, array $query = [], bool $decodeJson = false): array|string
    {
        $response = $this->sendRequest($method, $path, $body, 'application/octet-stream', $query);
        $contents = $this->readBody($response);
        $this->checkStatus($response->getStatusCode(), $contents);

        if ($decodeJson) {
            return $this->decodeBody($contents);
        }
        return $contents;
    }

    private function sendRequest(
        string $method,
        string $path,
        string $body,
        string $contentType,
        array $query = []
    ): ResponseInterface {
        $uri = $this->baseUri . $path;
        if (!empty($query)) {
            $uri .= '?' . http_build_query($query);
        }

        $request = $this->requestFactory->createRequest($method, $uri);

        if ($this->token !== null && $this->token !== '') {
            $request = $request->withHeader('X-Consul-Token', $this->token);
        }

        if ($body !== '') {
            $stream = $this->streamFactory->createStream($body);
            $request = $request->withBody($stream)
                ->withHeader('Content-Type', $contentType);
        }

        $this->logger->debug("Consul request: $method $uri");

        try {
            return $this->httpClient->sendRequest($request);
        } catch (Throwable $e) {
            $this->logger->debug('Consul HTTP transport error: ' . $e->getMessage());
            throw new ClientException('HTTP transport error', 0, $e);
        }
    }

    private function readBody(ResponseInterface $response): string
    {
        try {
            return (string) $response->getBody();
        } catch (RuntimeException $e) {
            throw new ClientException("Failed to read response body: " . $e->getMessage(), 0, $e);
        }
    }

    private function checkStatus(int $statusCode, string $contents): void
    {
        $body = $this->truncateErrorBody($contents);

        if ($statusCode >= 500) {
            throw new ServerException("Consul server error [$statusCode]: $body", $statusCode);
        }

        $class = match ($statusCode) {
            401 => UnauthorizedException::class,
            403 => AccessDeniedException::class,
            404 => NotFoundException::class,
            default => null,
        };

        if ($class !== null) {
            throw new $class("Consul request error [$statusCode]: $body", $statusCode);
        }

        if ($statusCode >= 400) {
            throw new ConsulRequestException("Consul request error [$statusCode]: $body", $statusCode);
        }
    }

    private function truncateErrorBody(string $contents): string
    {
        if (strlen($contents) <= self::MAX_ERROR_BODY_LENGTH) {
            return $contents;
        }
        return substr($contents, 0, self::MAX_ERROR_BODY_LENGTH) . '...';
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
        $rawBody = '';
        if (!empty($body)) {
            $json = json_encode($body);
            if ($json === false) {
                throw new ConsulRequestException('Failed to encode request body: ' . json_last_error_msg());
            }
            $rawBody = $json;
        }

        $response = $this->sendRequest($method, $path, $rawBody, 'application/json', $query);
        $contents = $this->readBody($response);
        $this->checkStatus($response->getStatusCode(), $contents);

        return $this->decodeBody($contents);
    }
}
