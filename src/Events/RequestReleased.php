<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class RequestReleased
{
    public function __construct(public Application $app)
    {
    }
}
