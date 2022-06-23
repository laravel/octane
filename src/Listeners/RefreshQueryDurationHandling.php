<?php

namespace Laravel\Octane\Listeners;

class RefreshQueryDurationHandling
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
            if (
                method_exists($connection, 'resetTotalQueryDuration')
                && method_exists($connection, 'allowQueryDurationHandlersToRunAgain')
            ) {
                $connection->resetTotalQueryDuration();
                $connection->allowQueryDurationHandlersToRunAgain();
            }
        }
    }
}
