<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Session\DatabaseSessionHandler;

class GiveNewApplicationInstanceToDatabaseSessionHandler
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('session')) {
            return;
        }

        $handler = $event->sandbox->make('session')->driver()->getHandler();

        if (! $handler instanceof DatabaseSessionHandler ||
            ! method_exists($handler, 'setContainer')) {
            return;
        }

        $handler->setContainer($event->sandbox);
    }
}
