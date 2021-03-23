<?php

namespace Laravel\Octane\Listeners;

use Laravel\Octane\Stream;

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
            Stream::error($event->exception);
        }
    }
}
