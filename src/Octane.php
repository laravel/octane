<?php

namespace Laravel\Octane;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Cache;
use Laravel\Octane\Events\TickReceived;
use Throwable;

class Octane
{
    use Concerns\ProvidesConcurrencySupport;
    use Concerns\ProvidesDefaultConfigurationOptions;
    use Concerns\ProvidesRouting;

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
            new Swoole\InvokeTickCallable(
                $key,
                $callback,
                $seconds,
                $immediate,
                Cache::store('octane'), app(ExceptionHandler::class)
            )
        );
    }

    /**
     * Format an exception to a string that should be returned to the client.
     *
     * @param  \Throwable  $e
     * @param  bool  $debug
     * @return string
     */
    public static function formatExceptionForClient(Throwable $e, $debug = false): string
    {
        return $debug ? (string) $e : 'Internal server error.';
    }
}
