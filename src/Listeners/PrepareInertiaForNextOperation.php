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
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(ResponseFactory::class)) {
            return;
        }

        $factory = $event->sandbox->make(ResponseFactory::class);

        if (method_exists($factory, 'flushShared')) {
            $factory->flushShared();
        }
    }
}
