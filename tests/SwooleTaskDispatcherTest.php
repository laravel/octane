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
    public function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function test_tasks_can_only_be_resolved_via_server_context()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->expectException(InvalidArgumentException::class);

        $this->assertEquals([
            'first' => 1,
        ], $dispatcher->resolve([
            'first' => fn () => 1,
        ]));
    }

    /** @test */
    public function test_tasks_timeout()
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
    public function test_tasks_propagate_exceptions()
    {
        $dispatcher = new SwooleTaskDispatcher();

        $this->instance(Server::class, Mockery::mock(Server::class, function ($mock) {
            $mock->shouldReceive('taskWaitMulti')
                ->once()
                ->andReturn([TaskExceptionResult::from(new Exception('foo'))]);
        }));

        $this->expectException(TaskException::class);

        $dispatcher->resolve(['first' => fn () => 1]);
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
