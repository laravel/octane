<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Broadcasting\BroadcastManager;

class GiveNewApplicationInstanceToBroadcastManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved(BroadcastManager::class)) {
            return;
        }

        with($event->sandbox->make(BroadcastManager::class), function ($manager) use ($event) {
            $manager->setApplication($event->sandbox);

            // Forgetting drivers will flush all channel routes which is unwanted...
            // $manager->forgetDrivers();
        });
    }
}
