<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Octane\Stream;

class ReportException
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
            Stream::throwable($event->exception);

            $event->sandbox[ExceptionHandler::class]->report($event->exception);
        }
    }
}
