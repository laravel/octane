<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class OperationReleased
{
    public function __construct(public Application $app)
    {
    }
}
