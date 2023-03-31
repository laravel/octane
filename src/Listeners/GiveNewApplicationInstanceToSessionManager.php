<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToSessionManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('session')) {
            return;
        }

        with($event->sandbox->make('session'), function ($manager) use ($event) {
            if (method_exists($manager, 'setContainer')) {
                $manager->setContainer($event->sandbox);
            }
        });
    }
}
