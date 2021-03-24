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
     * Register a callback to be called every N seconds.
     *
     * @param  string  $key
     * @param  callable  $callback
     * @param  int  $seconds
     * @param  bool  $immediate
     * @return void
     */
    public function tick(string $key, callable $callback, int $seconds = 1, bool $immediate = true)
    {
        app(Dispatcher::class)->listen(
            TickReceived::class,
            new InvokeTickCallable(
                $key,
                $callback,
                $seconds,
                $immediate,
                Cache::store('octane'),
                app(ExceptionHandler::class)
            )
        );
    }
}
