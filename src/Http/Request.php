<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/** 最小可用的 PSR-7 请求实现。 */
final class Request implements RequestInterface
{
    use MessageTrait;

    private string $method;

    private UriInterface $uri;

    private ?string $requestTarget = null;

    /**
     * @param string|UriInterface      $uri
     * @param array<string, string|string[]> $headers
     * @param string|StreamInterface   $body
     */
    public function __construct(
        string $method,
        $uri,
        array $headers = [],
        $body = '',
        string $version = '1.1'
    ) {
        $this->method = strtoupper($method);
        $this->uri = $uri instanceof UriInterface ? $uri : new Uri((string) $uri);
        $this->protocolVersion = $version;
        $this->body = $body instanceof StreamInterface ? $body : new Stream((string) $body);

        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = [(string) $name, self::normalizeValue($value)];
        }
    }

    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }
        if ($this->uri->getQuery() !== '') {
            $target .= '?' . $this->uri->getQuery();
        }

        return $target;
    }

    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        $clone = clone $this;
        $clone->requestTarget = $requestTarget;

        return $clone;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): RequestInterface
    {
        $clone = clone $this;
        $clone->method = strtoupper($method);

        return $clone;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        $clone = clone $this;
        $clone->uri = $uri;

        if (!$preserveHost || !$this->hasHeader('Host')) {
            $host = $uri->getHost();
            if ($host !== '') {
                $clone = $clone->withHeader('Host', $uri->getPort() !== null ? $host . ':' . $uri->getPort() : $host);
            }
        }

        return $clone;
    }
}
