<?php

namespace Laravel\Octane\Listeners;

use Inertia\ResponseFactory;

class PrepareInertiaForNextOperation
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if (! $this->app->resolved(ResponseFactory::class)) {
            return;
        }

        $factory = $this->app->make(ResponseFactory::class);

        if (method_exists($factory, 'flushShared')) {
            $factory->flushShared();
        }
    }
}
