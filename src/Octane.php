<?php

namespace Laravel\Octane;

use Laravel\Octane\Contracts\DispatchesCoroutines;
use Laravel\Octane\Contracts\DispatchesTasks;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Laravel\Octane\Swoole\SwooleTaskDispatcher;
use Swoole\Http\Server;
use Throwable;

class Octane
{
    use ProvidesDefaultConfigurationOptions;
    use ProvidesRouting;

    /**
     * Get the coroutine dispatcher.
     *
     * @return \Laravel\Contracts\Octane\DispatchesCoroutines
     */
    public function coroutines()
    {
        return app(DispatchesCoroutines::class);
    }

    /**
     * Get the task dispatcher.
     *
     * @return \Laravel\Contracts\Octane\DispatchesTasks
     */
    public function tasks()
    {
        return match(true) {
            app()->bound(DispatchesTasks::class) => app(DispatchesTasks::class),
            app()->bound(Server::class) => new SwooleTaskDispatcher,
            class_exists(Server::class) => new SwooleHttpTaskDispatcher(
                app(ServerStateFile::class),
                new SequentialTaskDispatcher
            ),
            default => new SequentialTaskDispatcher,
        };
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
