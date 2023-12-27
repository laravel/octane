<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class WebSocketMessage
{
    public function __construct(
        public Application $app,
        public Server $server,
        public Frame $frame
    ) {
    }
}
