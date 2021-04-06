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
        $event->sandbox->make('router')->setContainer($event->sandbox);
    }
}
