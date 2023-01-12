<?php

namespace Laravel\Octane\Listeners;

class ResetPreventQueriesState
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

        foreach ($event->sandbox['db']->getConnections() as $connection) {
            $connection->allowQueries();
        }
    }
}
