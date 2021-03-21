<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class TickReceived
{
    public function __construct(
        public Application $app,
        public Application $sandbox
    ) {
    }
}
