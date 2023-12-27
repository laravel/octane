<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\Swoole\WorkerState;
use Swoole\WebSocket\Server;

class OnWebSocketOpen
{
    public function __construct(
        protected Server $server,
        protected array $serverState,
        protected WorkerState $workerState
    ) {
    }

    /**
     * Handle the "open" Swoole event.
     */
    public function __invoke(Server $server, int $fd): void
    {
        $this->workerState->worker->handleWebSocketOpen($server, $fd);
    }
}
