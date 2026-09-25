<?php

namespace Erikwang2013\Consul\Tests\Http;

use Erikwang2013\Consul\Http\Stream;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Stream 是发布出去的 PSR-7 实现：库内部只走 getContents()/__toString()，
 * read()/tell()/rewind() 只有外部消费者会调，所以得在这里钉住。
 */
class StreamTest extends TestCase
{
    public function testReadReturnsRequestedBytesAndKeepsPosition(): void
    {
        $stream = new Stream('hello world');

        $this->assertSame('hello', $stream->read(5));
        $this->assertSame(' ', $stream->read(1));
        $this->assertSame('world', $stream->getContents());
    }

    public function testReadPastEofReturnsEmptyString(): void
    {
        $stream = new Stream('ab');

        $this->assertSame('ab', $stream->read(10));
        $this->assertTrue($stream->eof());
        $this->assertSame('', $stream->read(10));
    }

    public function testReadZeroLengthReturnsEmptyStringWithoutConsuming(): void
    {
        $stream = new Stream('ab');

        $this->assertSame('', $stream->read(0));
        $this->assertSame('ab', $stream->getContents());
    }

    public function testReadNegativeLengthThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('读取长度不能为负');

        (new Stream('ab'))->read(-1);
    }

    public function testReadOnClosedStreamThrows(): void
    {
        $stream = new Stream('ab');
        $stream->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('流不可读');

        $stream->read(1);
    }

    public function testReadOnDetachedStreamThrows(): void
    {
        $stream = new Stream('ab');
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('流不可读');

        $stream->read(1);
    }

    public function testTellTracksPositionAndRewindResetsIt(): void
    {
        $stream = new Stream('hello');

        $this->assertSame(0, $stream->tell());
        $stream->read(3);
        $this->assertSame(3, $stream->tell());

        $stream->rewind();
        $this->assertSame(0, $stream->tell());
        $this->assertSame('hello', $stream->getContents());
    }

    public function testTellOnDetachedStreamThrows(): void
    {
        $stream = new Stream('hello');
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('流不可用或无法取得位置');

        $stream->tell();
    }

    public function testReadableAndWritableFollowTheUnderlyingMode(): void
    {
        $readable = new Stream('x');
        $this->assertTrue($readable->isReadable());
        $this->assertTrue($readable->isWritable());   // php://temp 以 r+ 打开

        // php://temp 无论请求什么模式都报 w+b，所以只读/只写得用真实文件
        $file = (string) tempnam(sys_get_temp_dir(), 'consul-stream');

        try {
            $readOnly = new Stream(fopen($file, 'r'));
            $this->assertTrue($readOnly->isReadable());
            $this->assertFalse($readOnly->isWritable());

            $appendOnly = new Stream(fopen($file, 'a'));
            $this->assertFalse($appendOnly->isReadable());
            $this->assertTrue($appendOnly->isWritable());
            $this->assertSame(3, $appendOnly->write('abc'));

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('流不可读');
            $appendOnly->read(1);
        } finally {
            unlink($file);
        }
    }

    public function testSeekOnNonSeekableStreamThrowsAndStringCastStaysSafe(): void
    {
        $handle = popen('echo consul', 'r');
        $this->assertIsResource($handle);

        $stream = new Stream($handle);

        try {
            $this->assertFalse($stream->isSeekable());
            $this->assertSame('', (string) $stream, '__toString 不该把不可定位的流变成异常');

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('流不可定位');
            $stream->seek(0);
        } finally {
            pclose($handle);
        }
    }

    public function testWriteOnReadOnlyStreamThrows(): void
    {
        $file = (string) tempnam(sys_get_temp_dir(), 'consul-stream');
        $stream = new Stream(fopen($file, 'r'));

        try {
            $this->assertFalse($stream->isWritable());

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('流不可写');
            $stream->write('x');
        } finally {
            unlink($file);
        }
    }

    public function testGetContentsOnClosedStreamThrows(): void
    {
        $stream = new Stream('x');
        $stream->close();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('流已关闭');

        $stream->getContents();
    }
}
