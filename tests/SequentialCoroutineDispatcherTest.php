<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\SequentialCoroutineDispatcher;

class SequentialCoroutineDispatcherTest extends TestCase
{
    /** @test */
    public function test_coroutines_can_be_resolved()
    {
        $dispatcher = new SequentialCoroutineDispatcher;

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
            'third' => null,
        ], $dispatcher->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
            'third' => function () {},
        ]));
    }
}
