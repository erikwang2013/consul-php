<?php

namespace Erikwang2013\Consul\Tests\Config;

use Erikwang2013\Consul\Api\Kv;
use Erikwang2013\Consul\Config\Watcher;
use PHPUnit\Framework\TestCase;

class WatcherTest extends TestCase
{
    public function testOnChangeRegistersCallback(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->onChange(function ($snap) { });

        $this->assertInstanceOf(Watcher::class, $result);
    }

    public function testSetBlockingWaitIsFluent(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->setBlockingWait(60);

        $this->assertSame($watcher, $result);
    }

    public function testSetPollIntervalIsFluent(): void
    {
        $kv = $this->createMock(Kv::class);
        $watcher = new Watcher($kv, 'app/');

        $result = $watcher->setPollInterval(15);

        $this->assertSame($watcher, $result);
    }
}
