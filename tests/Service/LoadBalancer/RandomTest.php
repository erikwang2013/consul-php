<?php

namespace Erikwang2013\Consul\Tests\Service\LoadBalancer;

use Erikwang2013\Consul\Service\LoadBalancer\Random;
use PHPUnit\Framework\TestCase;

class RandomTest extends TestCase
{
    public function testSelectReturnsAnInstance(): void
    {
        $random = new Random();
        $instances = [
            ['address' => '10.0.0.1', 'port' => 80],
            ['address' => '10.0.0.2', 'port' => 80],
        ];

        $selected = $random->select($instances);

        $this->assertNotNull($selected);
        $this->assertContains($selected['address'], ['10.0.0.1', '10.0.0.2']);
    }

    public function testSelectReturnsNullWhenEmpty(): void
    {
        $random = new Random();
        $this->assertNull($random->select([]));
    }
}
