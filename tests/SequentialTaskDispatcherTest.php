<?php

namespace Laravel\Octane\Tests;

use Exception;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\SequentialTaskDispatcher;

class SequentialTaskDispatcherTest extends TestCase
{
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
            'third' => function () {
            },
        ]));
    }

    public function test_resolving_tasks_with_exceptions_do_not_effect_other_tasks()
    {
        $this->createApplication();
        $dispatcher = new SequentialTaskDispatcher;

        $a = false;

        try {
            $dispatcher->resolve([
                'first' => fn () => throw new Exception('Something went wrong'),
                'second' => function () use (&$a) {
                    $a = true;
                },
            ]);
        } catch (TaskException) {
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

    public function test_resolving_tasks_propagate_exceptions()
    {
        $this->createApplication();
        $dispatcher = new SequentialTaskDispatcher();

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Something went wrong.');

        $dispatcher->resolve([
            'first' => fn () => throw new Exception('Something went wrong.'),
        ]);
    }

    public function test_resolving_tasks_propagate_dd_calls()
    {
        $this->createApplication();
        $dispatcher = new SequentialTaskDispatcher();

        $this->expectException(DdException::class);
        $this->expectExceptionMessage(json_encode(['foo' => 'bar']));

        $dispatcher->resolve([
            'first' => fn () => throw new DdException(['foo' => 'bar']),
        ]);
    }
}
