<?php

namespace Erikwang2013\Consul\Tests\Http;

use Erikwang2013\Consul\Http\Request;
use Erikwang2013\Consul\Http\Uri;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function testWithMethodUppercasesAndIsImmutable(): void
    {
        $request = new Request('get', 'http://consul.local:8500/v1/kv');

        $this->assertSame('DELETE', $request->withMethod('delete')->getMethod());
        $this->assertSame('DELETE', $request->withMethod('DELETE')->getMethod());
        $this->assertSame('GET', $request->getMethod());
    }

    public function testRequestTargetFallsBackToSlashForEmptyPath(): void
    {
        $this->assertSame('/', (new Request('GET', 'http://consul.local:8500'))->getRequestTarget());
        $this->assertSame(
            '/v1/kv?recurse=1',
            (new Request('GET', 'http://consul.local:8500/v1/kv?recurse=1'))->getRequestTarget()
        );
    }

    public function testWithRequestTargetOverridesUri(): void
    {
        $request = new Request('GET', 'http://consul.local:8500/v1/kv');

        $this->assertSame('*', $request->withRequestTarget('*')->getRequestTarget());
        $this->assertSame('/v1/kv', $request->getRequestTarget());
    }

    public function testWithUriReplacesHostHeaderByDefault(): void
    {
        $request = new Request('GET', 'http://consul.local:8500/v1/kv', ['Host' => 'consul.local:8500']);
        $changed = $request->withUri(new Uri('http://other.internal/v1/kv'));

        $this->assertSame('other.internal', $changed->getHeaderLine('Host'));
        $this->assertSame('consul.local:8500', $request->getHeaderLine('Host'));
        $this->assertSame('other.internal', $changed->getUri()->getHost());
    }

    public function testWithUriWithPortIncludedInHostHeader(): void
    {
        $changed = (new Request('GET', 'http://consul.local:8500/v1/kv'))
            ->withUri(new Uri('http://other.internal:8501/v1/kv'));

        $this->assertSame('other.internal:8501', $changed->getHeaderLine('Host'));
    }

    public function testWithUriPreservesExistingHostHeaderWhenAsked(): void
    {
        $request = new Request('GET', 'http://consul.local:8500/v1/kv', ['Host' => 'consul.local:8500']);
        $changed = $request->withUri(new Uri('http://other.internal:8501/v1/kv'), true);

        $this->assertSame('consul.local:8500', $changed->getHeaderLine('Host'));
        $this->assertSame('other.internal', $changed->getUri()->getHost());
    }

    public function testWithUriPreserveHostStillSetsHostWhenMissing(): void
    {
        $request = new Request('GET', 'http://consul.local:8500/v1/kv');
        $changed = $request->withUri(new Uri('http://other.internal/v1/kv'), true);

        $this->assertSame('other.internal', $changed->getHeaderLine('Host'));
    }

    public function testWithUriWithoutHostKeepsRequestHostless(): void
    {
        $request = new Request('GET', 'http://consul.local:8500/v1/kv');
        $changed = $request->withUri(new Uri('*'));

        $this->assertFalse($changed->hasHeader('Host'));
        $this->assertSame('*', $changed->getUri()->getPath());
    }
}
