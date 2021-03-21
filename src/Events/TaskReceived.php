<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

class TaskReceived
{
    public function __construct(
        public Application $app,
        public Application $sandbox,
        public $data
    ) {
    }
}
