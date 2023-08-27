<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToValidationFactory
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('validator')) {
            return;
        }

        with($event->sandbox->make('validator'), function ($factory) use ($event) {
            $factory->setContainer($event->sandbox);
        });
    }
}
