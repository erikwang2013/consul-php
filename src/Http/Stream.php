<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * 最小可用的 PSR-7 流实现。
 *
 * 请求体走内存字符串，响应体走 php://temp（超过 2MB 自动落盘），够 HTTP 客户端用。
 */
final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    private ?int $size;

    /**
     * @param resource|string $content
     */
    public function __construct($content = '', ?int $size = null)
    {
        if (is_resource($content)) {
            $this->resource = $content;
            $this->size = $size;

            return;
        }

        $resource = fopen('php://temp', 'r+');
        if ($resource === false) {
            throw new RuntimeException('无法创建临时流');
        }

        if ($content !== '') {
            fwrite($resource, $content);
            rewind($resource);
        }

        $this->resource = $resource;
        $this->size = $size ?? strlen($content);
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        try {
            $this->seek(0);

            return (string) stream_get_contents($this->resource);
        } catch (RuntimeException) {
            return '';
        }
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
        }

        $this->resource = null;
        $this->size = null;
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->size = null;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        return $stats === false ? $this->size : $stats['size'];
    }

    public function tell(): int
    {
        $position = $this->resource === null ? false : ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('流不可用或无法取得位置');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->metadata('seekable') === true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable() || $this->resource === null || fseek($this->resource, $offset, $whence) !== 0) {
            throw new RuntimeException('流不可定位');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        $mode = (string) $this->metadata('mode');

        return strpbrk($mode, 'waxc+') !== false;
    }

    public function write(string $string): int
    {
        if (!$this->isWritable() || $this->resource === null) {
            throw new RuntimeException('流不可写');
        }

        $written = fwrite($this->resource, $string);
        if ($written === false) {
            throw new RuntimeException('写入流失败');
        }

        $this->size = null;

        return $written;
    }

    public function isReadable(): bool
    {
        $mode = (string) $this->metadata('mode');

        return str_contains($mode, 'r') || str_contains($mode, '+');
    }

    public function read(int $length): string
    {
        if (!$this->isReadable() || $this->resource === null) {
            throw new RuntimeException('流不可读');
        }

        if ($length < 0) {
            throw new InvalidArgumentException('读取长度不能为负');
        }

        if ($length === 0) {
            return '';
        }

        $data = fread($this->resource, $length);
        if ($data === false) {
            throw new RuntimeException('读取流失败');
        }

        return $data;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('流已关闭');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('读取流失败');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null)
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $metadata = stream_get_meta_data($this->resource);

        return $key === null ? $metadata : ($metadata[$key] ?? null);
    }

    private function metadata(string $key)
    {
        return $this->getMetadata($key);
    }
}
