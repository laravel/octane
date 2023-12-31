<?php

namespace Laravel\Octane\Swoole\Handlers;

use Laravel\Octane\Swoole\WorkerState;
use Swoole\Http\Request;
use Swoole\Http\Response;
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
    public function __invoke(Request $request, ?Response $response = null): void
    {
        $handshakeHandler = $this->serverState['octaneConfig']['swoole']['handler_handshake'] ?? OnWebSocketHandshake::class;

        if ($response && ! app()->make($handshakeHandler)->handle($request, $response)) {
            return;
        }

        $this->workerState->worker->handleWebSocketOpen($this->server, $request);
    }
}
