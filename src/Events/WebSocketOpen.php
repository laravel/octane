<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;
use Swoole\WebSocket\Server;

class WebSocketOpen
{
    public function __construct(
        public Application $app,
        public Server $server,
    ) {
    }
}
