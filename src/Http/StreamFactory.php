<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Http;

use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** 最小可用的 PSR-17 流工厂。 */
final class StreamFactory implements StreamFactoryInterface
{
    public function createStream(string $content = ''): StreamInterface
    {
        return new Stream($content);
    }

    public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
    {
        if (!is_file($filename)) {
            throw new RuntimeException("文件不存在：{$filename}");
        }

        $resource = @fopen($filename, $mode);
        if ($resource === false) {
            throw new RuntimeException("无法打开文件：{$filename}");
        }

        return new Stream($resource);
    }

    public function createStreamFromResource($resource): StreamInterface
    {
        if (!is_resource($resource)) {
            throw new RuntimeException('createStreamFromResource 需要一个有效的资源');
        }

        return new Stream($resource);
    }
}
