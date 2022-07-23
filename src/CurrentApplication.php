<?php

namespace Laravel\Octane;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelRay\RayServiceProvider;

class CurrentApplication
{
    /**
     * Set the current application in the container.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    public static function set(Application $app): void
    {
        $app->instance('app', $app);
        $app->instance(Container::class, $app);

        Container::setInstance($app);

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        (function () use ($app) {
            /** @var ServiceProvider $provider */
            foreach ($this->serviceProviders as $provider) {
                // Skip providers already handled specifically in Octane
                if ($provider instanceof OctaneServiceProvider ||
                    $provider instanceof RayServiceProvider
                ) {
                    continue;
                }

                (function () use ($app) {
                    $this->app = $app;
                })->call($provider);

                $this->bootProvider($provider);
            }
        })->call($app);
    }
}
