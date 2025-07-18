<?php

namespace Laravel\Octane\Tests;

use Laravel\Octane\Swoole\Handlers\OnWorkerStart;
use Laravel\Octane\Swoole\SwooleExtension;
use Mockery;

class OnWorkerStartTest extends TestCase
{
    public function test_should_clear_opcache_returns_true_by_default(): void
    {
        $this->createApplication();
        
        // Mock the OnWorkerStart handler to test just the shouldClearOpcodeCache method
        $handler = Mockery::mock(OnWorkerStart::class)->makePartial();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('shouldClearOpcodeCache');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($handler));
    }

    public function test_should_clear_opcache_returns_configured_value(): void
    {
        $app = $this->createApplication();
        $app['config']['octane.swoole.clear_opcache'] = false;
        
        // Mock the OnWorkerStart handler to test just the shouldClearOpcodeCache method
        $handler = Mockery::mock(OnWorkerStart::class)->makePartial();
        
        $reflection = new \ReflectionClass($handler);
        $method = $reflection->getMethod('shouldClearOpcodeCache');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($handler));
    }
}