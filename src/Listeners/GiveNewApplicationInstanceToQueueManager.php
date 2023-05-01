<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToQueueManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('queue')) {
            return;
        }

        with($event->sandbox->make('queue'), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);
        });
    }
}
