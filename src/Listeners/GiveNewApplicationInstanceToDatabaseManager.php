<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToDatabaseManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('db')) {
            return;
        }

        with($event->sandbox->make('db'), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);
        });
    }
}
