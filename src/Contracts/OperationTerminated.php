<?php

namespace Laravel\Octane\Contracts;

use Illuminate\Foundation\Application;

interface OperationTerminated
{
    /**
     * Get the base application instance.
     */
    public function app(): Application;

    /**
     * Get the sandbox version of the application instance.
     */
    public function sandbox(): Application;
}
