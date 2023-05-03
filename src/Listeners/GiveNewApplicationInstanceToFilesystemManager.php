<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToFilesystemManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('filesystem')) {
            return;
        }

        with($event->sandbox->make('filesystem'), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);
        });
    }
}
