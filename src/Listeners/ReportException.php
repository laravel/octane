<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Laravel\Octane\Exceptions\DdException;
use Laravel\Octane\Stream;

class ReportException
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if ($event->exception) {
            tap($event->sandbox, function ($sandbox) use ($event) {
                if ($event->exception instanceof DdException) {
                    return;
                }

                if ($sandbox->environment('local', 'testing')) {
                    Stream::throwable($event->exception);
                }

                $sandbox[ExceptionHandler::class]->report($event->exception);
            });
        }
    }
}
