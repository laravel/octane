<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\SequentialTaskDispatcher;

class SequentialTaskDispatcherTest extends TestCase
{
    /** @test */
    public function test_tasks_can_be_resolved()
    {
        $dispatcher = new SequentialTaskDispatcher;

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

    /** @test */
    public function test_tasks_can_be_dispatched()
    {
        $dispatcher = new SequentialTaskDispatcher;

        $first = null;
        $second = null;

        $dispatcher->dispatch([
            'first' => function () use (&$first) {
                $first = 'first';
            },
            'second' => function () use (&$second) {
                $second = 'second';
            },
        ]);

        $this->assertEquals('first', $first);
        $this->assertEquals('second', $second);
    }
}
