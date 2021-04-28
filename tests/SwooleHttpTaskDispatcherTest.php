<?php

namespace Laravel\Octane\Tests;

use Exception;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Laravel\Octane\SequentialTaskDispatcher;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Orchestra\Testbench\TestCase;

class SwooleHttpTaskDispatcherTest extends TestCase
{
    public function test_tasks_can_be_resolved_via_http()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(serialize(['first' => 1, 'second' => 2, 'third' => null])),
        ]);

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

    /** @doesNotPerformAssertions @test */
    public function test_tasks_can_be_dispatched_via_http()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/dispatch-tasks' => Http::response(serialize(['first' => 1, 'second' => 2])),
        ]);

        $dispatcher->dispatch([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]);
    }

    public function test_tasks_can_be_resolved_via_fallback_dispatcher()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
        ], $dispatcher->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]));
    }

    /** @doesNotPerformAssertions @test */
    public function test_tasks_can_be_dispatched_via_fallback_dispatcher()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        $dispatcher->dispatch([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]);
    }

    public function test_resolving_tasks_propagate_exceptions()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(null, 500),
        ]);

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Invalid response from task server.');

        $dispatcher->resolve(['first' => fn () => throw new Exception('Something went wrong.')]);
    }

    public function test_resolving_tasks_propagate_dd_calls()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(null, 500),
        ]);

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Invalid response from task server.');

        $dispatcher->resolve(['first' => fn () => throw new DdException(['foo' => 'bar'])]);
    }

    /** @doesNotPerformAssertions @test */
    public function test_dispatching_tasks_do_not_propagate_exceptions()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/dispatch-tasks' => Http::response(null, 500),
        ]);

        $dispatcher->dispatch(['first' => fn () => throw new Exception('Something went wrong.')]);
    }

    public function test_resolving_tasks_may_timeout()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(null, 504),
        ]);

        $this->expectException(TaskTimeoutException::class);
        $this->expectExceptionMessage('Task timed out after 2000 milliseconds.');

        $dispatcher->resolve(['first' => fn () => 1], 2000);
    }

    protected function getPackageProviders($app)
    {
        return ['Laravel\Octane\OctaneServiceProvider'];
    }
}
