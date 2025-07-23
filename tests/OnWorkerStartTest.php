<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\Handlers\OnWorkerStart;
use Laravel\Octane\Swoole\SwooleExtension;
use Laravel\Octane\Swoole\WorkerState;
use Mockery;

class OnWorkerStartTest extends TestCase
{
    public function test_should_clear_opcache_returns_true_by_default(): void
    {
        // Mock the OnWorkerStart handler to test just the shouldClearOpcodeCache method
        $handler = Mockery::mock(OnWorkerStart::class)->makePartial();

        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('shouldClearOpcodeCache');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($handler));
    }

    public function test_should_clear_opcache_returns_configured_value(): void
    {
        $extension = Mockery::mock(SwooleExtension::class);
        $workerState = Mockery::mock(WorkerState::class);
        $basePath = realpath(__DIR__.'/../vendor/orchestra/testbench-core/laravel');

        // Mock the OnWorkerStart handler to test just the shouldClearOpcodeCache method
        $handler = Mockery::mock(OnWorkerStart::class, [
            $extension,
            $basePath,
            ['octaneConfig' => [
                'swoole' => [
                    'clear_opcache' => false,
                ],
            ]],
            $workerState,
        ])->makePartial();

        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('shouldClearOpcodeCache');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($handler));
    }
}
