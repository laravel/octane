<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToCacheManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('cache')) {
            return;
        }

        with($event->sandbox->make('cache'), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);
        });
    }
}
