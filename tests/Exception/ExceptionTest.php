<?php

namespace Erikwang2013\Consul\Tests\Exception;

use Erikwang2013\Consul\Exception\AccessDeniedException;
use Erikwang2013\Consul\Exception\ClientException;
use Erikwang2013\Consul\Exception\ConsulException;
use Erikwang2013\Consul\Exception\ConsulRequestException;
use Erikwang2013\Consul\Exception\NotFoundException;
use Erikwang2013\Consul\Exception\ServerException;
use Erikwang2013\Consul\Exception\UnauthorizedException;
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
        $this->assertInstanceOf(ConsulRequestException::class, new UnauthorizedException());
    }

    public function testConsulExceptionIsRuntimeException(): void
    {
        $this->assertInstanceOf(\RuntimeException::class, new ConsulException());
    }

    public function testServerExceptionIsNotRequestException(): void
    {
        $this->assertNotInstanceOf(ConsulRequestException::class, new ServerException());
    }

    public function testMessageAndCodeArePreserved(): void
    {
        $e = new ConsulException('boom', 42);

        $this->assertSame('boom', $e->getMessage());
        $this->assertSame(42, $e->getCode());
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        $previous = new \RuntimeException('root cause');
        $e = new ClientException('wrapped', 0, $previous);

        $this->assertSame($previous, $e->getPrevious());
    }

    public function testSpecificExceptionsCarryStatusCode(): void
    {
        $e = new NotFoundException('missing', 404);

        $this->assertSame(404, $e->getCode());
        $this->assertInstanceOf(ConsulException::class, $e);
    }
}
