<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\Concurrent;
use Swoole\Coroutine;

class ConcurrentTest extends TestCase
{
    public function test_concurrent()
    {
        Coroutine::create(function () {
            $concurrent = new Concurrent($limit = 10);
            $this->assertSame($limit, $concurrent->getLimit());
            $this->assertTrue($concurrent->isEmpty());
            $this->assertFalse($concurrent->isFull());

            $count = 0;
            for ($i = 0; $i < 15; $i++) {
                $concurrent->create(function () use (&$count) {
                    Coroutine::sleep(0.1);
                    $count++;
                });
            }

            $this->assertTrue($concurrent->isFull());
            $this->assertSame(5, $count);
            $this->assertSame($limit, $concurrent->getRunningCoroutineCount());
            $this->assertSame($limit, $concurrent->getLength());
            $this->assertSame($limit, $concurrent->length());

            while (! $concurrent->isEmpty()) {
                Coroutine::sleep(0.1);
            }

            $this->assertSame(15, $count);
        });
    }

    public function test_exception()
    {
        Coroutine::create(function () {
            $concurrent = new Concurrent(10);
            $count = 0;

            for ($i = 0; $i < 15; $i++) {
                $concurrent->create(function () use (&$count) {
                    Coroutine::sleep(0.1);
                    $count++;
                    throw new \Exception('ddd');
                });
            }

            $this->assertSame(5, $count);
            $this->assertSame(10, $concurrent->getRunningCoroutineCount());

            while (! $concurrent->isEmpty()) {
                Coroutine::sleep(0.1);
            }

            $this->assertSame(15, $count);
        });
    }
}
