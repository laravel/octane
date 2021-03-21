<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;
use Laravel\Octane\Contracts\OperationTerminated;

class TickTerminated implements OperationTerminated
{
    use HasApplicationAndSandbox;

    public function __construct(
        public Application $app,
        public Application $sandbox
    ) {
    }
}
