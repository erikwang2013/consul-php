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

    public function testExecutorRunsOnlyOnceAcrossMultipleWaits(): void
    {
        $runs = 0;
        $promise = new Promise(function () use (&$runs) {
            $runs++;
            return 'value';
        });

        $this->assertSame('value', $promise->wait());
        $this->assertSame('value', $promise->wait());
        $this->assertSame(1, $runs);
    }

    public function testThenRegisteredAfterResolutionRunsImmediately(): void
    {
        $promise = new Promise(function () {
            return 7;
        });
        $promise->wait();

        $received = null;
        $promise->then(function ($value) use (&$received) {
            $received = $value;
        });

        $this->assertSame(7, $received);
    }

    public function testCatchRegisteredAfterRejectionRunsImmediately(): void
    {
        $promise = $this->rejectedPromise();
        try {
            $promise->wait();
        } catch (RuntimeException $e) {
        }

        $caught = null;
        $promise->catch(function ($e) use (&$caught) {
            $caught = $e->getMessage();
        });

        $this->assertSame('boom', $caught);
    }

    public function testMultipleThenCallbacksRunInOrderOnWait(): void
    {
        $order = [];
        $promise = new Promise(function () {
            return 'x';
        });
        $promise->then(function () use (&$order) {
            $order[] = 'first';
        });
        $promise->then(function () use (&$order) {
            $order[] = 'second';
        });

        $promise->wait();

        $this->assertSame(['first', 'second'], $order);
    }

    public function testThenReturnsSelfForChaining(): void
    {
        $promise = new Promise(function () {
            return 1;
        });

        $this->assertSame($promise, $promise->then(function ($v) {
        }));
    }

    public function testWaitReturnsNullValueOnFulfilledPromise(): void
    {
        $promise = new Promise(function () {
            return null;
        });

        $this->assertNull($promise->wait());
    }
}
