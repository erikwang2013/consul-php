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
use InvalidArgumentException;
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
    private int $retries;
    private int $retryDelayMs;

    public function __construct(
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        string $baseUri = 'http://127.0.0.1:8500',
        ?string $token = null,
        ?LoggerInterface $logger = null,
        int $retries = 0,
        int $retryDelayMs = 50
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->baseUri = self::normalizeBaseUri($baseUri);
        $this->token = $token;
        $this->logger = $logger ?? new NullLogger();
        $this->retries = max(0, $retries);
        $this->retryDelayMs = max(0, $retryDelayMs);
    }

    /**
     * 环境变量里抄来的地址常常没写 scheme（`127.0.0.1:8500`），
     * 直接拼出来的 URI 会让 HTTP 客户端报一句无从下手的"传输错误"，这里补上默认 http://。
     */
    private static function normalizeBaseUri(string $baseUri): string
    {
        $baseUri = trim($baseUri);
        if ($baseUri === '') {
            throw new InvalidArgumentException('Consul base_uri 不能为空');
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $baseUri) !== 1) {
            $baseUri = 'http://' . $baseUri;
        }

        return rtrim($baseUri, '/');
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

    public function deleteWithBody(string $path, array $body = [], array $query = []): array
    {
        return $this->request('DELETE', $path, $body, $query);
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
        $queryString = $query === [] ? '' : self::buildQueryString($query);
        if ($queryString !== '') {
            $uri .= '?' . $queryString;
        }

        // 只对幂等方法重试：Consul 的 GET/PUT/DELETE 都是幂等的，POST 不保证
        $attempts = in_array($method, ['GET', 'HEAD', 'PUT', 'DELETE', 'OPTIONS'], true)
            ? max(1, $this->retries + 1)
            : 1;
        $delayMs = $this->retryDelayMs;

        for ($attempt = 1; ; $attempt++) {
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

                if ($attempt >= $attempts) {
                    throw new ClientException('HTTP transport error', 0, $e);
                }

                $this->logger->warning(sprintf(
                    'Consul 请求失败，%d/%d 后重试：%s',
                    $attempt,
                    $attempts - 1,
                    $e->getMessage()
                ));
                usleep($delayMs * 1000);
                $delayMs *= 2;   // 退避
            }
        }
    }


    /**
     * 组装查询串。数组值展开成**重复键**（`?name=a&name=b`）：
     * `http_build_query` 会编成 `name[0]=a`，而 Consul（Go 的 url.Values）读的是重复的纯键——
     * `node-meta`、intention 的 `name` 都是这种形态，之前各模块只能在内部手工拼串绕过。
     * 不含数组时仍走 http_build_query，保持既有输出逐字节不变。
     *
     * @param array<string, mixed> $query
     */
    private static function buildQueryString(array $query): string
    {
        foreach ($query as $value) {
            if (!is_array($value)) {
                continue;
            }

            $parts = [];
            foreach ($query as $name => $items) {
                foreach (is_array($items) ? $items : [$items] as $item) {
                    $parts[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $item);
                }
            }

            return implode('&', $parts);
        }

        return http_build_query($query);
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

        // 按字符边界截断：直接 substr 会把多字节字符切成两半，
        // 异常消息里就成了非法 UTF-8（写日志或再 json_encode 时炸）
        if (\function_exists('mb_strcut')) {
            return mb_strcut($contents, 0, self::MAX_ERROR_BODY_LENGTH, 'UTF-8') . '...';
        }

        if (preg_match('/^.{0,' . self::MAX_ERROR_BODY_LENGTH . '}/us', $contents, $matches) === 1) {
            return $matches[0] . '...';
        }

        // 输入本身就不是合法 UTF-8（例如反代返回了二进制错误页），只能按字节截
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
