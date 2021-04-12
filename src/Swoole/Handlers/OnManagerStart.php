<?php

namespace Laravel\Octane\Swoole\Handlers;

use Illuminate\Support\Facades\Cache;
use Laravel\Octane\ApplicationFactory;
use Laravel\Octane\Swoole\SwooleExtension;
use Laravel\Octane\Swoole\WorkerState;

class OnManagerStart
{
    public function __construct(
        protected SwooleExtension $extension,
        protected array $serverState,
        protected mixed $bootstrap,
        protected WorkerState $workerState,
        protected bool $shouldSetProcessName = true
    ) {
    }

    /**
     * Handle the "managerstart" Swoole event.
     *
     * @return void
     */
    public function __invoke()
    {
        if ($this->shouldSetProcessName) {
            $this->extension->setProcessName($this->serverState['appName'], 'manager process');
        }

        $this->restoreOctaneCache(($this->bootstrap)($this->serverState));
    }

    /**
     * Restore the octane cache that we stored on "managerstop" event.
     *
     * @param  string $basePath
     * @return void
     */
    protected function restoreOctaneCache(string $basePath)
    {
        $tmpApp = new ApplicationFactory($basePath);
        $tmpApp->createApplication();

        $cache = (array) Cache::get('octane-cache');

        foreach ($cache as $key => $row) {
            $this->workerState->cacheTable[$key] = $row;
        }

        unset($tmpApp, $cache);
    }
}
