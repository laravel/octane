<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToLogManager
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('log')) {
            return;
        }

        $event->sandbox->make('log')->setApplication($event->sandbox);
    }
}

