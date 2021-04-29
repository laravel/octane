<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\SequentialCoroutineDispatcher;

class SequentialCoroutineDispatcherTest extends TestCase
{
    public function test_coroutines_can_be_resolved(): void
    {
        $dispatcher = new SequentialCoroutineDispatcher;

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
            'third' => null,
        ], $dispatcher->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
            'third' => function () {
            },
        ]));
    }
}
