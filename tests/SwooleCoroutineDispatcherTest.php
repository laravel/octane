<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\SwooleCoroutineDispatcher;
use Orchestra\Testbench\TestCase;

class SwooleCoroutineDispatcherTest extends TestCase
{
    /** @test */
    public function test_can_resolve_concurrently_callbacks()
    {
        $dispatcher = new SwooleCoroutineDispatcher(false);

        $callbacks = [
            fn() => 1,
            fn() => 2,
        ];

        $results = $dispatcher->resolve($callbacks, 0);
        sort($results);

        $this->assertEquals([1, 2], $results);
    }
}
