<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class WorkerStopping
{
    public function __construct(public Application $app)
    {
    }
}
