<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToDatabaseManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('db') ||
            ! method_exists($event->sandbox->make('db'), 'setApplication')) {
            return;
        }

        with($event->sandbox->make('db'), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);
        });
    }
}
