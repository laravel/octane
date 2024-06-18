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

        if (method_exists($log = $event->sandbox->make('log'), 'setApplication')) {
            $log->setApplication($event->sandbox);
        }
    }
}
