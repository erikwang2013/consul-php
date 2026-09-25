<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;

/**
 * Request / Response 共用的消息行为：协议版本、头、体。
 */
trait MessageTrait
{
    private string $protocolVersion = '1.1';

    /** @var array<string, array{0: string, 1: list<string>}> 键为小写头名，值为 [原始大小写名, 值列表] */
    private array $headers = [];

    private ?StreamInterface $body = null;

    public function getProtocolVersion(): string
    {
        return $this->protocolVersion;
    }

    public function withProtocolVersion(string $version): static
    {
        $clone = clone $this;
        $clone->protocolVersion = $version;

        return $clone;
    }

    public function getHeaders(): array
    {
        $headers = [];
        foreach ($this->headers as [$name, $values]) {
            $headers[$name] = $values;
        }

        return $headers;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[strtolower($name)]);
    }

    public function getHeader(string $name): array
    {
        return $this->headers[strtolower($name)][1] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(', ', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        self::assertHeaderValue($name, $value);

        $clone = clone $this;
        $clone->headers[strtolower($name)] = [$name, self::normalizeValue($value)];

        return $clone;
    }

    public function withAddedHeader(string $name, $value): static
    {
        self::assertHeaderValue($name, $value);

        $clone = clone $this;
        $key = strtolower($name);
        $values = self::normalizeValue($value);
        $clone->headers[$key] = [$this->headers[$key][0] ?? $name, [...($this->headers[$key][1] ?? []), ...$values]];

        return $clone;
    }

    public function withoutHeader(string $name): static
    {
        $clone = clone $this;
        unset($clone->headers[strtolower($name)]);

        return $clone;
    }

    public function getBody(): StreamInterface
    {
        return $this->body ??= new Stream();
    }

    public function withBody(StreamInterface $body): static
    {
        $clone = clone $this;
        $clone->body = $body;

        return $clone;
    }

    /** @param string|string[] $value */
    private static function normalizeValue(array|string $value): array
    {
        return array_values(array_map(static fn ($item): string => trim((string) $item), is_array($value) ? $value : [$value]));
    }

    /** @param mixed $value */
    private static function assertHeaderValue(string $name, $value): void
    {
        if (preg_match('/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/', $name) !== 1) {
            throw new InvalidArgumentException("非法的头名：{$name}");
        }

        $values = is_array($value) ? $value : [$value];
        foreach ($values as $item) {
            if (!is_string($item) && !is_numeric($item)) {
                throw new InvalidArgumentException('头值必须是字符串');
            }
            if (preg_match('/[\r\n]/', (string) $item) === 1) {
                throw new InvalidArgumentException('头值不能包含换行');
            }
        }
    }
}
