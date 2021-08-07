<?php

namespace Laravel\Octane\Listeners;

class GiveNewApplicationInstanceToRouter
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('router')) {
            return;
        }

        $event->sandbox->make('router')->setContainer($event->sandbox);
    }
}
