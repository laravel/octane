<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToEventDispatcher
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('events')) {
            return;
        }

        with($event->sandbox->make('events'), function ($dispatcher) use ($event) {
            $dispatcher->setQueueResolver(fn () => $event->sandbox->make('queue'));
        });
    }
}
