<?php

namespace Laravel\Octane\Concerns;

use Laravel\Octane\Contracts\DispatchesCoroutines;
use Laravel\Octane\Contracts\DispatchesTasks;
use Laravel\Octane\SequentialTaskDispatcher;
use Laravel\Octane\Swoole\ServerStateFile;
use Laravel\Octane\Swoole\SwooleHttpTaskDispatcher;
use Laravel\Octane\Swoole\SwooleTaskDispatcher;
use Swoole\Http\Server;

trait ProvidesConcurrencySupport
{
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
            class_exists(Server::class) => (fn (array $serverState) => new SwooleHttpTaskDispatcher(
                $serverState['state']['host'] ?? '127.0.0.1',
                $serverState['state']['port'] ?? '8000',
                new SequentialTaskDispatcher
            ))(app(ServerStateFile::class)->read()),
            default => new SequentialTaskDispatcher,
        };
    }
}
