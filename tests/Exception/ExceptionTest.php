<?php

namespace Erikwang\Consul\Tests\Exception;

use Erikwang\Consul\Exception\AccessDeniedException;
use Erikwang\Consul\Exception\ClientException;
use Erikwang\Consul\Exception\ConsulException;
use Erikwang\Consul\Exception\ConsulRequestException;
use Erikwang\Consul\Exception\NotFoundException;
use Erikwang\Consul\Exception\ServerException;
use PHPUnit\Framework\TestCase;

class ExceptionTest extends TestCase
{
    public function testExceptionHierarchy(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new ConsulException());
        $this->assertInstanceOf(ConsulException::class, new ClientException());
        $this->assertInstanceOf(ConsulException::class, new ServerException());
        $this->assertInstanceOf(ConsulException::class, new ConsulRequestException());
        $this->assertInstanceOf(ConsulRequestException::class, new NotFoundException());
        $this->assertInstanceOf(ConsulRequestException::class, new AccessDeniedException());
    }
}
