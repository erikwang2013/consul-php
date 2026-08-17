<?php

declare(strict_types=1);

namespace Erikwang2013\Consul\Tests\Client;

use Erikwang2013\Consul\Client\Promise;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PromiseTest extends TestCase
{
    private function rejectedPromise(): Promise
    {
        return new Promise(function () {
            throw new RuntimeException('boom');
        });
    }

    public function testWaitThrowsExecutorException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $this->rejectedPromise()->wait();
    }

    public function testSecondWaitStillThrowsAfterRejection(): void
    {
        $promise = $this->rejectedPromise();
        try {
            $promise->wait();
        } catch (RuntimeException $e) {
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $promise->wait();
    }

    public function testThenAfterRejectionThrows(): void
    {
        $promise = $this->rejectedPromise();
        try {
            $promise->wait();
        } catch (RuntimeException $e) {
        }

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');

        $promise->then(function ($value) {
        });
    }

    public function testCatchOnFulfilledReturnsSelfWithoutQueuing(): void
    {
        $promise = new Promise(function () {
            return 'ok';
        });
        $promise->wait();

        $caught = false;
        $result = $promise->catch(function ($e) use (&$caught) {
            $caught = true;
        });

        $this->assertSame($promise, $result);
        $this->assertFalse($caught);
    }
}
