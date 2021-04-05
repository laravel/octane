<?php

namespace Laravel\Octane\Listeners;

class FlushLocaleState
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        $event->sandbox->forgetInstance('translator');
    }
}
