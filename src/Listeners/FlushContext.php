<?php

namespace Laravel\Octane\Listeners;

use Illuminate\Log\Context\Repository;

class FlushContext
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if ($event->sandbox->resolved(Repository::class)) {
            $event->sandbox->make(Repository::class)->flush()->setApplication($event->sandbox);
        }
    }
}

