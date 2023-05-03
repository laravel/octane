<?php

namespace Laravel\Octane;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;

class CurrentApplication
{
    /**
     * Set the current application in the container.
     */
    public static function set(Application $app): void
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        Container::setInstance($app);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);
    }
}
