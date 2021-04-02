<?php

namespace Laravel\Octane;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Application;

trait DispatchesEvents
{
    /**
     * Dispatch the given event via the given application.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @param  mixed  $event
     * @return void
     */
    public function dispatchEvent(Application $app, $event): void
    {
        if ($app->bound(Dispatcher::class)) {
            $app[Dispatcher::class]->dispatch($event);
        }
    }
}
