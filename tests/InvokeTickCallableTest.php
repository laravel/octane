<?php

namespace Laravel\Octane\Tests;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Carbon;
use Laravel\Octane\Swoole\InvokeTickCallable;
use Mockery;

class InvokeTickCallableTest extends TestCase
{
    /** @test */
    public function test_callable_is_invoked_when_due()
    {
        Carbon::setTestNow($now = now());

        $instance = new InvokeTickCallable(
            'key', fn () => $_SERVER['__test.invokeTickCallable'] = true, 1, true,
            $cache = Mockery::mock('stdClass'), Mockery::mock(ExceptionHandler::class)
        );

        $cache->shouldReceive('get')->with('tick-key')->andReturn(time() - 100);

        $cache->shouldReceive('forever')->once()->with('tick-key', $now->getTimestamp());

        $instance();

        $this->assertTrue($_SERVER['__test.invokeTickCallable'] ?? false);

        Carbon::setTestNow();
        unset($_SERVER['__test.invokeTickCallable']);
    }

    /** @test */
    public function test_callable_is_not_invoked_when_not_due()
    {
        Carbon::setTestNow($now = now());

        $_SERVER['__test.invokeTickCallable'] = false;

        $instance = new InvokeTickCallable(
            'key', fn () => $_SERVER['__test.invokeTickCallable'] = true, 30, true,
            $cache = Mockery::mock('stdClass'), Mockery::mock(ExceptionHandler::class)
        );

        $cache->shouldReceive('get')->with('tick-key')->andReturn(time() - 10);

        $cache->shouldReceive('forever')->never();

        $instance();

        $this->assertFalse($_SERVER['__test.invokeTickCallable'] ?? false);

        Carbon::setTestNow();
        unset($_SERVER['__test.invokeTickCallable']);
    }

    /** @test */
    public function test_callable_is_invoked_when_first_run_and_immediate()
    {
        Carbon::setTestNow($now = now());

        $instance = new InvokeTickCallable(
            'key', fn () => $_SERVER['__test.invokeTickCallable'] = true, 1, true,
            $cache = Mockery::mock('stdClass'), Mockery::mock(ExceptionHandler::class)
        );

        $cache->shouldReceive('get')->with('tick-key')->andReturn(null);

        $cache->shouldReceive('forever')->once()->with('tick-key', $now->getTimestamp());

        $instance();

        $this->assertTrue($_SERVER['__test.invokeTickCallable'] ?? false);

        Carbon::setTestNow();
        unset($_SERVER['__test.invokeTickCallable']);
    }

    /** @test */
    public function test_callable_is_not_invoked_when_first_run_and_not_immediate()
    {
        Carbon::setTestNow($now = now());

        $instance = new InvokeTickCallable(
            'key', fn () => $_SERVER['__test.invokeTickCallable'] = true, 1, false,
            $cache = Mockery::mock('stdClass'), Mockery::mock(ExceptionHandler::class)
        );

        $cache->shouldReceive('get')->with('tick-key')->andReturn(null);

        $cache->shouldReceive('forever')->once()->with('tick-key', $now->getTimestamp());

        $instance();

        $this->assertFalse($_SERVER['__test.invokeTickCallable'] ?? false);

        Carbon::setTestNow();
        unset($_SERVER['__test.invokeTickCallable']);
    }
}
