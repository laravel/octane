<?php

namespace Laravel\Octane\Events;

use Swoole\Http\Server;

class MainServerStarting
{
    public function __construct(public Server $server)
    {
    }
}
