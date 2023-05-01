<?php

namespace Laravel\Octane\Events;

use Illuminate\Foundation\Application;

trait HasApplicationAndSandbox
{
    /**
     * Get the base application instance.
     */
    public function app(): Application
    {
        return $this->app;
    }

    /**
     * Get the sandbox version of the application instance.
     */
    public function sandbox(): Application
    {
        return $this->sandbox;
    }
}
