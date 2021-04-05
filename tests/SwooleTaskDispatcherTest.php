<?php

namespace Laravel\Octane\Tests;

use Exception;
use InvalidArgumentException;
use Laravel\Octane\Exceptions\TaskException;
use Laravel\Octane\Exceptions\TaskExceptionResult;
use Laravel\Octane\Exceptions\TaskTimeoutException;
use Laravel\Octane\Swoole\SwooleTaskDispatcher;
use Mockery;
use Orchestra\Testbench\TestCase;
use Swoole\Http\Server;

class SwooleTaskDispatcherTest extends TestCase
{
    /** @test */
    public function test_tasks_can_only_be_resolved_via_server_context()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->expectException(InvalidArgumentException::class);

        $dispatcher->resolve(['first' => fn () => 1]);
    }

    /** @test */
    public function test_tasks_can_only_be_dispatched_via_server_context()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->expectException(InvalidArgumentException::class);

        $dispatcher->dispatch(['first' => fn () => 1]);
    }

    /** @test */
    public function test_resolving_tasks_may_timeout()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->instance(Server::class, Mockery::mock(Server::class, function ($mock) {
            $mock->shouldReceive('taskWaitMulti')
                ->once()
                ->andReturn(false);
        }));

        $this->expectException(TaskTimeoutException::class);
        $this->expectExceptionMessage('Task timed out after 2000 milliseconds.');

        $dispatcher->resolve(['first' => fn () => 1], 2000);
    }

    /** @test */
    public function test_resolving_tasks_propagate_exceptions()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->instance(Server::class, Mockery::mock(Server::class, function ($mock) {
            $mock->shouldReceive('taskWaitMulti')
                ->once()
                ->andReturn([TaskExceptionResult::from(new Exception('Something went wrong.'))]);
        }));

        $this->expectException(TaskException::class);
        $this->expectExceptionMessage('Something went wrong.');

        $dispatcher->resolve(['first' => fn () => 1]);
    }

    public function test_dispatching_tasks_do_not_propagate_exceptions()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->instance(Server::class, Mockery::mock(Server::class, function ($mock) {
            $mock->shouldReceive('task')
                ->once();
        }));

        $dispatcher->dispatch(['first' => fn () => throw new Exception('Something went wrong.')]);
    }

    /** @test */
    public function test_tasks_can_be_resolved()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->instance(Server::class, Mockery::mock(Server::class, function ($mock) {
            $mock->shouldReceive('taskWaitMulti')
                ->once()
                ->andReturn([1, 2]);
        }));

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
        ], $dispatcher->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]));
    }
}
