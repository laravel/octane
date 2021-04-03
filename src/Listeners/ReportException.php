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
            tap($event->sandbox, function ($sandbox) use ($event) {
                if ($sandbox->environment('local')) {
                    Stream::throwable($event->exception);
                }

                $sandbox[ExceptionHandler::class]->report($event->exception);
            });
        }
    }
}
