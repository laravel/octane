<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\Swoole\ServerStateFile;

class OnServerShutdown
{
    public function __construct(protected ServerStateFile $serverStateFile)
    {
    }

    /**
     * Handle the "shutdown" Swoole event.
     *
     * @return void
     */
    public function __invoke()
    {
        $this->serverStateFile->delete();
    }
}
