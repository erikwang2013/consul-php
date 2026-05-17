<?php

namespace Erikwang2013\Consul\Tests\Exception;

use Erikwang2013\Consul\Exception\AccessDeniedException;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Exception\ConsulException;
use Erikwang2013\Consul\Exception\ConsulRequestException;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Exception\ServerException;
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
