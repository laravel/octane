<?php

namespace Laravel\Octane\Listeners;

class GiveNewRequestInstanceToApplication
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     */
    public function handle($event): void
    {
        $event->sandbox->instance('request', $event->request);
    }
}
