<?php

namespace Laravel\Octane\Listeners;

class GiveNewRequestInstanceToApplication
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        $event->app->instance('request', $event->request);
        $event->sandbox->instance('request', $event->request);
    }
}
