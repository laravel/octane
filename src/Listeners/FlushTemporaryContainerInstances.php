<?php

namespace Laravel\Octane\Listeners;

class FlushTemporaryContainerInstances
{
    public function __construct()
    {
        $app = app();
        if (method_exists($app, 'resetScope')) {
            $app->resetScope();
        }

        if (method_exists($app, 'forgetScopedInstances')) {
            $app->forgetScopedInstances();
        }

        foreach ($app->make('config')->get('octane.flush', []) as $binding) {
            $app->forgetInstance($binding);
        }
    }

    public function handle($event): void
    {
        // nothing to do here
        // flushing the instances on startup is enogh
    }
}
