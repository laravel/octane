<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class TaskTerminated
{
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public $data,
        public $result,
    ) {
    }
}
