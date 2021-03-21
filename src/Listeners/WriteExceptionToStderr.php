<?php

namespace Laravel\Octane\Listeners;

class WriteExceptionToStderr
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event)
    {
        if ($event->exception) {
            fwrite(STDERR, (string) $event->exception);
        }
    }
}
