<?php

namespace Laravel\Octane\Contracts;

use Illuminate\Foundation\Application;

interface OperationTerminated
{
    /**
     * Get the base application instance.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function app(): Application;

    /**
     * Get the sandbox version of the application instance.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function sandbox(): Application;
}
