<?php

namespace Laravel\Octane\Concerns;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Events\TickReceived;
use Laravel\Octane\Swoole\InvokeTickCallable;

trait RegistersTickHandlers
{
    /**
     * Register a callback to be called every N milliseconds.
     *
     * @return \Laravel\Octane\Swoole\InvokeTickCallable
     */
    public function tick(string $key, callable $callback, int $milliseconds = 1000, bool $immediate = true)
    {
        $listener = new InvokeTickCallable(
            $key,
            $callback,
            $milliseconds,
            $immediate,
            Cache::store('octane'),
            app(ExceptionHandler::class)
        );

        app(Dispatcher::class)->listen(
            TickReceived::class,
            $listener
        );

        return $listener;
    }
}
