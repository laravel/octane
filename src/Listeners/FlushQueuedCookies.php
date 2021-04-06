<?php

namespace Laravel\Octane\Listeners;

class FlushQueuedCookies
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        $event->sandbox->make('cookie')->flushQueuedCookies();
    }
}
