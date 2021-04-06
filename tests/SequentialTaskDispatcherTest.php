<?php

namespace Laravel\Octane\Tests;

use Exception;
use Laravel\Octane\Exceptions\TaskException;
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
    public function test_resolving_tasks_with_exceptions_do_not_effect_other_tasks()
    {
        $dispatcher = new SequentialTaskDispatcher;

        $a = false;

        try {
            $dispatcher->resolve([
                'first' => fn () => throw new Exception('Something went wrong'),
                'second' => function () use (&$a) {
                    $a = true;
                },
            ]);
        } catch (TaskException $e) {
            //
        }

        $this->assertTrue($a);
    }

    /** @doesNotPerformAssertions @test */
    public function test_dispatching_tasks_do_not_propagate_exceptions()
    {
        $dispatcher = new SequentialTaskDispatcher;

        $dispatcher->dispatch([
            'first' => fn () => throw new Exception('Something went wrong'),
        ]);
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

    /** @test */
    public function test_resolving_tasks_propagate_exceptions()
    {
        $dispatcher = new SequentialTaskDispatcher();

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Something went wrong.');

        $dispatcher->resolve([
            'first' => fn () => throw new Exception('Something went wrong.'),
        ]);
    }
}
