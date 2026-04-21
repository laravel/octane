<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\RouteCollection;

class GiveNewApplicationInstanceToRouter
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('router')) {
            return;
        }

        $event->sandbox->make('router')->setContainer($event->sandbox);

        if (! $event->sandbox->resolved('routes')) {
            return;
        }

        $routes = $event->sandbox->make('routes');

        if (! $routes instanceof RouteCollection && ! $routes instanceof CompiledRouteCollection) {
            return;
        }

        if ($routes instanceof CompiledRouteCollection) {
            $routes->setContainer($event->sandbox);

            foreach ((function () {
                return $this->nameCache ?? [];
            })->call($routes) as $route) {
                $route->setContainer($event->sandbox);
            }
        }

        foreach ($routes as $route) {
            $route->setContainer($event->sandbox);
        }
    }
}
