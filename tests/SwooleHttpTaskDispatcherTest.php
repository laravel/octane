<?php

namespace Laravel\Octane\Tests;

use Exception;
use Illuminate\Support\Facades\Http;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\SequentialTaskDispatcher;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Orchestra\Testbench\TestCase;

class SwooleHttpTaskDispatcherTest extends TestCase
{
    /** @test */
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

    /** @test */
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

    /** @test */
    public function test_tasks_propagates_original_exceptions()
    {
        $dispatcher = new SwooleHttpTaskDispatcher(
            '127.0.0.1',
            '8000',
            new SequentialTaskDispatcher,
        );

        $exception = null;
        $result = null;

        try {
            $result = $dispatcher->resolve([
                'first' => fn () => 1,
                'second' => fn () => throw new Exception('Something went wrong.', 128),
            ]);
        } catch (Exception $exception) {
            //
        }

        $this->assertNull($result);
        $this->assertInstanceOf(TaskException::class, $exception);
        $this->assertEquals(Exception::class, $exception->getClass());
        $this->assertEquals('Something went wrong.', $exception->getMessage());
        $this->assertEquals(128, $exception->getCode());
        $this->assertEquals(__FILE__, $exception->getFile());
        $this->assertEquals(91, $exception->getLine());
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

    protected function getPackageProviders($app)
    {
        return ['Laravel\Octane\OctaneServiceProvider'];
    }
}
