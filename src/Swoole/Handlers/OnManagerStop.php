<?php

namespace Laravel\Octane\Swoole\Handlers;

use Illuminate\Support\Facades\Cache;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Swoole\WorkerState;

class OnManagerStop
{
    public function __construct(
        protected string $basePath,
        protected WorkerState $workerState
    ) {
    }

    /**
     * Handle the "managerstop" Swoole event.
     *
     * @return void
     */
    public function __invoke()
    {
        $tmpApp = new ApplicationFactory($this->basePath);
        $tmpApp->createApplication();

        $cache = [];

        foreach ($this->workerState->cacheTable as $key => $row) {
            $cache[$key] = $row;
        }

        Cache::put('octane-cache', $cache);

        unset($tmpApp, $cache);
    }
}
