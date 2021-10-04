<?php

namespace Laravel\Octane\Listeners;

class FlushDatabaseQueryLog
{
    /**
     * Handle the event.
     *
     * @param  mixed  $event
     * @return void
     */
    public function handle($event): void
    {
        if (! $event->sandbox->resolved('db')) {
            return;
        }

        foreach ($event->sandbox->make('db')->getConnections() as $connection) {
            $connection->flushQueryLog();
        }
    }
}
