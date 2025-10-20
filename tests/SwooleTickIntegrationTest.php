<?php

namespace Laravel\Octane\Tests;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Carbon;
use Laravel\Octane\Swoole\InvokeTickCallable;
use Mockery;
use Orchestra\Testbench\TestCase;

class SwooleTickIntegrationTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('swoole') && ! extension_loaded('openswoole')) {
            $this->markTestSkipped('Swoole or OpenSwoole extension is not installed.');
        }
    }

    public function test_tick_callable_executes_with_millisecond_precision()
    {
        $cache = Mockery::mock(Repository::class);
        $executionCount = 0;

        $callback = function () use (&$executionCount) {
            $executionCount++;
        };

        $tickCallable = new InvokeTickCallable(
            'test-tick', // key
            $callback, // callback
            100, // milliseconds
            true, // immediate
            $cache, // cache
            Mockery::mock(ExceptionHandler::class) // exception handler
        );

        // Test first execution (should always execute)
        $cache->shouldReceive('get')->with('tick-test-tick')->andReturn(null)->once();
        $cache->shouldReceive('forever')->with('tick-test-tick', Mockery::any())->once();
        
        $tickCallable();
        $this->assertEquals(1, $executionCount, 'First execution should happen immediately');

        // Test second execution (should not execute - too soon)
        $now = Carbon::now();
        $cache->shouldReceive('get')->with('tick-test-tick')->andReturn($now->getTimestampMs() - 50)->once();
        
        $tickCallable();
        $this->assertEquals(1, $executionCount, 'Should not execute again too soon');

        // Test third execution (should execute - enough time passed)
        $cache->shouldReceive('get')->with('tick-test-tick')->andReturn($now->getTimestampMs() - 150)->once();
        $cache->shouldReceive('forever')->with('tick-test-tick', Mockery::any())->once();
        
        $tickCallable();
        $this->assertEquals(2, $executionCount, 'Should execute after sufficient time');
    }

    public function test_tick_callable_with_seconds_method_converts_correctly()
    {
        Carbon::setTestNow($now = now());
        
        $executed = false;
        
        // Test the seconds() method conversion
        $tickCallable = new InvokeTickCallable(
            'test-seconds',
            function () use (&$executed) {
                $executed = true;
            },
            1000, // Default milliseconds
            true,
            Mockery::mock('stdClass'),
            Mockery::mock(ExceptionHandler::class)
        );
        
        // Use the seconds() method to convert
        $tickCallable->seconds(1); // 1 second = 1000 milliseconds

        // Verify the internal milliseconds property is set correctly
        $reflection = new \ReflectionClass($tickCallable);
        $millisecondsProperty = $reflection->getProperty('milliseconds');
        $millisecondsProperty->setAccessible(true);
        
        $this->assertEquals(1000, $millisecondsProperty->getValue($tickCallable), 
            'seconds() method should convert 1 second to 1000 milliseconds');
    }

    public function test_tick_callable_with_milliseconds_method_sets_directly()
    {
        Carbon::setTestNow($now = now());
        
        $executed = false;
        
        // Test the milliseconds() method
        $tickCallable = new InvokeTickCallable(
            'test-milliseconds',
            function () use (&$executed) {
                $executed = true;
            },
            1000, // Default milliseconds
            true,
            Mockery::mock('stdClass'),
            Mockery::mock(ExceptionHandler::class)
        );
        
        // Use the milliseconds() method to set directly
        $tickCallable->milliseconds(500); // 500 milliseconds

        // Verify the internal milliseconds property is set correctly
        $reflection = new \ReflectionClass($tickCallable);
        $millisecondsProperty = $reflection->getProperty('milliseconds');
        $millisecondsProperty->setAccessible(true);
        
        $this->assertEquals(500, $millisecondsProperty->getValue($tickCallable), 
            'milliseconds() method should set milliseconds directly');
    }

    public function test_milliseconds_conversion_integration()
    {
        // Test that our milliseconds functionality integrates properly
        $cache = Mockery::mock(Repository::class);
        $executionCount = 0;

        $callback = function () use (&$executionCount) {
            $executionCount++;
        };

        // Test creating a tick callable with milliseconds directly
        $tickCallable = new InvokeTickCallable(
            'ms-test', // key
            $callback, // callback
            250, // 250 milliseconds
            false, // not immediate
            $cache, // cache
            Mockery::mock(ExceptionHandler::class) // exception handler
        );

        // Mock cache for first execution (should not execute because immediate=false)
        $cache->shouldReceive('get')->with('tick-ms-test')->andReturn(null)->once();
        $cache->shouldReceive('forever')->with('tick-ms-test', Mockery::any())->once();
        $tickCallable();
        $this->assertEquals(0, $executionCount, 'Should not execute immediately when immediate=false');

        // Mock cache for second execution (should execute because enough time passed)
        $now = Carbon::now();
        $cache->shouldReceive('get')->with('tick-ms-test')->andReturn($now->getTimestampMs() - 300)->once();
        $cache->shouldReceive('forever')->with('tick-ms-test', Mockery::any())->once();
        $tickCallable();
        $this->assertEquals(1, $executionCount, 'Should execute after 300ms when interval is 250ms');
    }

    public function test_fluent_methods_work_correctly()
    {
        // Test that the fluent methods exist and return the instance
        $cache = Mockery::mock(Repository::class);
        $callback = function () {};
        
        $tickCallable = new InvokeTickCallable(
            'fluent-test', // key
            $callback, // callback
            1000, // 1000 milliseconds
            false, // not immediate
            $cache, // cache
            Mockery::mock(ExceptionHandler::class) // exception handler
        );
        
        $result = $tickCallable->milliseconds(500);
        $this->assertInstanceOf(InvokeTickCallable::class, $result, 'milliseconds() should return the instance');
        
        $result2 = $tickCallable->seconds(2);
        $this->assertInstanceOf(InvokeTickCallable::class, $result2, 'seconds() should return the instance');
        
        $result3 = $tickCallable->immediate();
        $this->assertInstanceOf(InvokeTickCallable::class, $result3, 'immediate() should return the instance');
    }

    protected function tearDown(): void
    {
        // Clean up any remaining timers
        $timerClass = null;
        if (class_exists('\Swoole\Timer')) {
            $timerClass = '\Swoole\Timer';
        } elseif (class_exists('\OpenSwoole\Timer')) {
            $timerClass = '\OpenSwoole\Timer';
        }
        
        if ($timerClass) {
            foreach ($timerClass::list() as $timerId) {
                $timerClass::clear($timerId);
            }
        }
        
        Mockery::close();
        parent::tearDown();
    }
}