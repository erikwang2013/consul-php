<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

/** 最小可用的 PSR-7 响应实现。 */
final class Response implements ResponseInterface
{
    use MessageTrait;

    private const REASON_PHRASES = [
        200 => 'OK', 201 => 'Created', 202 => 'Accepted', 204 => 'No Content',
        301 => 'Moved Permanently', 302 => 'Found', 304 => 'Not Modified',
        400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden', 404 => 'Not Found',
        405 => 'Method Not Allowed', 409 => 'Conflict', 429 => 'Too Many Requests',
        500 => 'Internal Server Error', 502 => 'Bad Gateway', 503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
    ];

    private int $statusCode;

    private string $reasonPhrase;

    /**
     * @param array<string, string|string[]> $headers
     * @param string|StreamInterface         $body
     */
    public function __construct(
        int $status = 200,
        array $headers = [],
        $body = '',
        string $version = '1.1',
        ?string $reason = null
    ) {
        $this->statusCode = $status;
        $this->protocolVersion = $version;
        $this->body = $body instanceof StreamInterface ? $body : new Stream((string) $body);
        $this->reasonPhrase = $reason ?? self::REASON_PHRASES[$status] ?? '';

        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = [(string) $name, self::normalizeValue($value)];
        }
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        if ($code < 100 || $code > 599) {
            throw new InvalidArgumentException("非法的状态码：{$code}");
        }

        $clone = clone $this;
        $clone->statusCode = $code;
        $clone->reasonPhrase = $reasonPhrase !== '' ? $reasonPhrase : (self::REASON_PHRASES[$code] ?? '');

        return $clone;
    }

    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase;
    }
}
