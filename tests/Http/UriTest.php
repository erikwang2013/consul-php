<?php

namespace Erikwang2013\Consul\Tests\Http;

use Erikwang2013\Consul\Http\Uri;
use PHPUnit\Framework\TestCase;

class UriTest extends TestCase
{
    public function testEmptyUriHasNoParts(): void
    {
        $uri = new Uri('');

        $this->assertSame('', $uri->getScheme());
        $this->assertSame('', $uri->getAuthority());
        $this->assertSame('', $uri->getHost());
        $this->assertNull($uri->getPort());
        $this->assertSame('', $uri->getPath());
        $this->assertSame('', $uri->getQuery());
        $this->assertSame('', $uri->getFragment());
        $this->assertSame('', (string) $uri);
    }

    public function testAuthorityIsEmptyWithoutHost(): void
    {
        $uri = new Uri('/v1/kv/app?recurse=1');

        $this->assertSame('', $uri->getAuthority());
        $this->assertSame('/v1/kv/app?recurse=1', (string) $uri);
    }

    public function testWithSchemeLowercasesAndIsImmutable(): void
    {
        $uri = new Uri('http://consul.local:8500/v1/kv');
        $changed = $uri->withScheme('HTTPS');

        $this->assertSame('https', $changed->getScheme());
        $this->assertSame('https://consul.local:8500/v1/kv', (string) $changed);
        $this->assertSame('http', $uri->getScheme());
        $this->assertSame('http://consul.local:8500/v1/kv', (string) $uri);
    }

    public function testWithUserInfo(): void
    {
        $uri = new Uri('http://consul.local:8500/v1/kv');

        $this->assertSame('user:secret@consul.local:8500', $uri->withUserInfo('user', 'secret')->getAuthority());
        $this->assertSame('user@consul.local:8500', $uri->withUserInfo('user')->getAuthority());
        $this->assertSame('', $uri->getUserInfo());
    }

    public function testWithHostLowercases(): void
    {
        $uri = new Uri('http://consul.local:8500/v1/kv');
        $changed = $uri->withHost('CONSUL.internal');

        $this->assertSame('consul.internal', $changed->getHost());
        $this->assertSame('consul.internal:8500', $changed->getAuthority());
        $this->assertSame('consul.local', $uri->getHost());
    }

    public function testWithHostOnHostlessUriBuildsAuthority(): void
    {
        $this->assertSame('consul.local', (new Uri('/v1/kv'))->withHost('consul.local')->getAuthority());
    }

    public function testWithPortAddsAndRemovesPort(): void
    {
        $uri = new Uri('http://consul.local/v1/kv');

        $this->assertSame('consul.local:8501', $uri->withPort(8501)->getAuthority());
        $this->assertNull($uri->withPort(8501)->withPort(null)->getPort());
        $this->assertSame('consul.local', $uri->withPort(8501)->withPort(null)->getAuthority());
    }

    public function testWithFragmentStripsLeadingHash(): void
    {
        $uri = new Uri('http://consul.local:8500/v1/kv');

        $this->assertSame('frag', $uri->withFragment('#frag')->getFragment());
        $this->assertSame('http://consul.local:8500/v1/kv#frag', (string) $uri->withFragment('#frag'));
        $this->assertSame('', $uri->getFragment());
    }
}
