<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Contracts\Container\Container;
use Illuminate\Events\Dispatcher;

class GiveNewApplicationInstanceToEventDispatcher
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        static::give($event->sandbox);
    }

    /**
     * Give the event dispatcher the container that should resolve class based listeners.
     */
    public static function give(Container $app): void
    {
        if (! $app->resolved('events')) {
            return;
        }

        $events = $app->make('events');

        if (method_exists($events, 'setContainer')) {
            $events->setContainer($app);
        } elseif ($events instanceof Dispatcher) {
            (fn () => $this->container = $app)->call($events);
        }
    }
}
