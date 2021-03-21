<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class WorkerStarting
{
    public function __construct(public Application $app)
    {
    }
}
