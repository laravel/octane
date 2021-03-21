<?php

namespace Laravel\Octane\Tests;

use Illuminate\Support\Facades\Http;
use Laravel\Octane\SequentialTaskDispatcher;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Mockery;
use Orchestra\Testbench\TestCase;

class SwooleHttpTaskDispatcherTest extends TestCase
{
    /** @test */
    public function test_tasks_can_be_resolved_via_http()
    {
        $serverStateFile = Mockery::mock(ServerStateFile::class);

        $serverStateFile->shouldReceive('read')->once()->andReturn([
            'state' => [
                'host' => '127.0.0.1',
                'port' => 8000,
            ],
        ]);

        $dispatcher = new SwooleHttpTaskDispatcher(
            $serverStateFile,
            new SequentialTaskDispatcher,
        );

        Http::fake([
            '127.0.0.1:8000/octane/resolve-tasks' => Http::response(serialize(['first' => 1, 'second' => 2])),
        ]);

        $this->assertEquals([
            'first' => 1,
            'second' => 2,
        ], $dispatcher->resolve([
            'first' => fn () => 1,
            'second' => fn () => 2,
        ]));
    }

    /** @test */
    public function test_tasks_can_be_dispatched_via_http()
    {
        $serverStateFile = Mockery::mock(ServerStateFile::class);

        $serverStateFile->shouldReceive('read')->once()->andReturn([
            'state' => [
                'host' => '127.0.0.1',
                'port' => 8000,
            ],
        ]);

        $dispatcher = new SwooleHttpTaskDispatcher(
            $serverStateFile,
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
        $serverStateFile = Mockery::mock(ServerStateFile::class);

        $serverStateFile->shouldReceive('read')->once()->andReturn([
            'state' => [
                'host' => '127.0.0.1',
                'port' => 8000,
            ],
        ]);

        $dispatcher = new SwooleHttpTaskDispatcher(
            $serverStateFile,
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
    public function test_tasks_can_be_dispatched_via_fallback_dispatcher()
    {
        $serverStateFile = Mockery::mock(ServerStateFile::class);

        $serverStateFile->shouldReceive('read')->once()->andReturn([
            'state' => [
                'host' => '127.0.0.1',
                'port' => 8000,
            ],
        ]);

        $dispatcher = new SwooleHttpTaskDispatcher(
            $serverStateFile,
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
