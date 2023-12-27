<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\Swoole\WorkerState;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class OnWebSocketMessage
{
    public function __construct(
        protected array $serverState,
        protected WorkerState $workerState
    ) {
    }

    /**
     * Handle the "message" Swoole event.
     */
    public function __invoke(Server $server, Frame $frame): void
    {
        $this->workerState->worker->handleWebSocketMessage($server, $frame);
    }
}
